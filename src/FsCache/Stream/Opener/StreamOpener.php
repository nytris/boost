<?php

/*
 * Nytris Boost
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/nytris/boost/
 *
 * Released under the MIT license.
 * https://github.com/nytris/boost/raw/main/MIT-LICENSE.txt
 */

declare(strict_types=1);

namespace Nytris\Boost\FsCache\Stream\Opener;

use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapperInterface;
use LogicException;
use Nytris\Boost\FsCache\Contents\CachedFileInterface;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\Realpath\RealpathCacheInterface;
use Nytris\Boost\FsCache\Stat\StatCacheInterface;
use WeakMap;

/**
 * Class StreamOpener.
 *
 * Opens a file stream, handling contents caching if applicable.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class StreamOpener implements StreamOpenerInterface
{
    /**
     * @var WeakMap<StreamWrapperInterface, array{cachedFile: CachedFileInterface, path: string}>
     */
    private WeakMap $writeStreamData;

    public function __construct(
        private readonly StreamHandlerInterface $wrappedStreamHandler,
        private readonly RealpathCacheInterface $realpathCache,
        private readonly StatCacheInterface $statCache,
        private readonly ?ContentsCacheInterface $contentsCache,
        private readonly bool $asVirtualFilesystem
    ) {
        $this->writeStreamData = new WeakMap();
    }

    /**
     * @inheritDoc
     */
    public function flushStream(
        StreamWrapperInterface $streamWrapper
    ): bool {
        if (!$this->asVirtualFilesystem || !$this->contentsCache) {
            return $this->wrappedStreamHandler->streamFlush($streamWrapper);
        }

        // Virtual filesystem mode - write contents of stream to contents cache if applicable.
        $writeStreamData = $this->writeStreamData[$streamWrapper] ?? null;

        if ($writeStreamData === null) {
            // Stream is not open for writing to a file in the virtual filesystem.
            return true;
        }

        ['cachedFile' => $cachedFile, 'path' => $path] = $writeStreamData;

        $resource = $streamWrapper->getWrappedResource();

        // TODO: Confirm whether this check is needed - if opened in read-only mode, will flush hook ever be called,
        //       even on fflush(...)?
        $mode = $streamWrapper->getOpenMode();
        $isRead = str_contains($mode, 'r') && !str_contains($mode, '+');

        if (!$isRead) {
            $position = ftell($resource);

            if ($position === false) {
                // Stream cannot be flushed as we cannot even read its current position.
                return false;
            }

            // Flushing the `php://memory` stream is probably not required, but included for completeness.
            if (fflush($resource) === false) {
                return false;
            }

            // Rewind as the stream is likely to currently be seeked following writes.
            rewind($resource);
            $contents = stream_get_contents($resource);

            // Seek back to the original position following the read just above.
            if (fseek($resource, $position) === -1) {
                return false;
            }

            $cachedFile->setContents($contents);

            try {
                $this->statCache->updateSyntheticStat(
                    $path,
                    isInclude: false,
                    size: strlen($contents)
                );
            } catch (LogicException) {
                return false;
            }
        }

        unset($this->writeStreamData[$streamWrapper]);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function openStream(
        StreamWrapperInterface $streamWrapper,
        string $path,
        string $mode,
        int $options,
        ?string &$openedPath
    ): ?array {
        $isRead = str_contains($mode, 'r') && !str_contains($mode, '+');

        if ($isRead) {
            $effectivePath = $this->realpathCache->getRealpath($path);

            if ($effectivePath === null) {
                return null;
            }
        } else {
            $effectivePath = $path;

            if (!$this->asVirtualFilesystem) {
                // File is being written to, so clear cache (e.g. in case it was cached as non-existent).
                $this->realpathCache->invalidatePath($effectivePath);
                $this->statCache->invalidatePath($effectivePath);
                $this->contentsCache?->invalidatePath($effectivePath);
            }
        }

        if ($this->contentsCache === null) {
            if ($this->asVirtualFilesystem) {
                // File contents access is impossible in virtual filesystem mode with no contents cache,
                // as there is nowhere to store or retrieve file contents from.
                return null;
            }

            // Contents cache is disabled, so ignore.
            return $this->wrappedStreamHandler->streamOpen($streamWrapper, $effectivePath, $mode, $options, $openedPath);
        }

        if (!$isRead) {
            if ($this->asVirtualFilesystem) {
                // Load file contents from the contents cache if possible.
                $cachedFile = $this->contentsCache->getItemForPath($effectivePath, isInclude: false);

                $writeStream = fopen('php://memory', 'rb+');

                if ($cachedFile->isCached()) {
                    // Read current contents from contents cache into stream, if any.
                    $contents = $cachedFile->getContents();

                    fwrite($writeStream, $contents);

                    // Unless we're opening for append, rewind the stream pointer to the beginning.
                    if (!str_contains($mode, 'a')) {
                        rewind($writeStream);
                    }

                    $this->statCache->updateSyntheticStat(
                        $effectivePath,
                        isInclude: false,
                        size: strlen($contents)
                    );
                } else {
                    // File is newly created and therefore empty.
                    $cachedFile->setContents('');

                    $this->statCache->synthesiseStat(
                        $effectivePath,
                        isInclude: false,
                        isDir: false,
                        mode: 0777,
                        size: 0,
                    );
                }

                $this->writeStreamData[$streamWrapper] = [
                    'cachedFile' => $cachedFile,
                    'path' => $effectivePath,
                ];

                return [
                    'resource' => $writeStream,
                    'isInclude' => false, // Cannot be an include in write mode.
                ];
            }

            // We are opening for write, so ignore - contents cache will have been cleared above.
            return $this->wrappedStreamHandler->streamOpen($streamWrapper, $effectivePath, $mode, $options, $openedPath);
        }

        if ($this->statCache->isDirectory($effectivePath)) {
            // When opening a directory as a stream, it is not actually readable or writable.
            // TODO: Raise `Read of 8192 bytes failed with errno=21 Is a directory` on read etc.

            return [
                'resource' => fopen('php://memory', 'rb+'),
                'isInclude' => false, // Cannot be an include of a directory.
            ];
        }

        $isInclude = $this->wrappedStreamHandler->isInclude($options);

        $cachedFile = $this->contentsCache->getItemForPath($effectivePath, $isInclude);

        if ($cachedFile->isCached()) {
            // File's contents are cached, so we can serve it without hitting the filesystem.

            $cacheStream = fopen('php://memory', 'rb+');
            $contents = $cachedFile->getContents();

            fwrite($cacheStream, $contents);
            rewind($cacheStream);

            if ($isInclude) {
                /*
                 * Populate the stat cache for the include if it hasn't already been.
                 *
                 * See notes in `->synthesiseStat(...)`.
                 */
                $this->statCache->populateStatWithSize(
                    $effectivePath,
                    strlen($contents),
                    isInclude: true
                );
            }

            $usePath = (bool) ($options & STREAM_USE_PATH);

            if ($usePath && $openedPath) {
                // This is usually done by the original StreamHandler, but when contents are cached
                // we do not return to there.
                $openedPath = $effectivePath;
            }

            return ['resource' => $cacheStream, 'isInclude' => $isInclude];
        }

        if ($this->asVirtualFilesystem) {
            if (!$isInclude) {
                // File is not cached - if we had tried the include variant then we could fall back
                // to the non-include variant and apply any shifts, but this file does not exist at all.
                return null;
            }

            // Attempt to read the non-shifted variant of the file.
            $cachedFile = $this->contentsCache->getItemForPath($effectivePath, isInclude: false);

            if (!$cachedFile->isCached()) {
                // File does not exist in the virtual filesystem at all.
                return null;
            }

            $nonIncludeStream = fopen('php://memory', 'rb+');
            fwrite($nonIncludeStream, $cachedFile->getContents());
            rewind($nonIncludeStream);

            $sourceStream = $this->wrappedStreamHandler->shiftFile(
                $effectivePath,
                fn () => $nonIncludeStream
            );

            if ($sourceStream === null) {
                // Failed to shift the file for some reason.
                return null;
            }
        } else {
            // In caching mode rather than virtual filesystem mode, and contents are not yet cached -
            // forward to the wrapped handler, which will also apply any applicable shifts.
            ['resource' => $sourceStream] = $this->wrappedStreamHandler->streamOpen(
                $streamWrapper,
                $effectivePath,
                $mode,
                $options,
                $openedPath
            );
        }

        $contents = stream_get_contents($sourceStream);

        // Now rewind the stream after reading its contents just above,
        // so that it can be reused for the current stream wrapper.
        rewind($sourceStream);

        $cachedFile->setContents($contents);

        if ($isInclude) {
            /*
             * Populate the stat cache for the include if it hasn't already been.
             *
             * See notes in `->synthesiseIncludeStat(...)`.
             */
            $this->statCache->populateStatWithSize(
                $effectivePath,
                strlen($contents),
                isInclude: true
            );
        }

        return ['resource' => $sourceStream, 'isInclude' => $isInclude];
    }
}
