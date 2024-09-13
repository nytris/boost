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
use Nytris\Boost\FsCache\CanonicaliserInterface;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\Stream\Handler\FsCachingStreamHandlerInterface;

/**
 * Class StreamOpener.
 *
 * Opens a file stream, handling contents caching if applicable.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class StreamOpener implements StreamOpenerInterface
{
    public function __construct(
        private readonly StreamHandlerInterface $wrappedStreamHandler,
        private readonly CanonicaliserInterface $canonicaliser,
        private readonly ?ContentsCacheInterface $contentsCache
    ) {
    }

    /**
     * @inheritDoc
     */
    public function openStream(
        StreamWrapperInterface $streamWrapper,
        string $path,
        string $mode,
        int $options,
        ?string &$openedPath,
        FsCachingStreamHandlerInterface $streamHandler
    ): ?array {
        $isRead = str_contains($mode, 'r') && !str_contains($mode, '+');

        if ($isRead) {
            $effectivePath = $streamHandler->getRealpath($path);

            if ($effectivePath === null) {
                return null;
            }
        } else {
            $effectivePath = $this->canonicaliser->canonicalise($path);

            // File is being written to, so clear cache (e.g. in case it was cached as non-existent).
            $streamHandler->invalidatePath($effectivePath);
        }

        if ($this->contentsCache === null) {
            // Contents cache is disabled, so ignore.
            return $this->wrappedStreamHandler->streamOpen($streamWrapper, $effectivePath, $mode, $options, $openedPath);
        }

        if (!$isRead) {
            // We are opening for write, so ignore - contents cache will have been cleared above.
            return $this->wrappedStreamHandler->streamOpen($streamWrapper, $effectivePath, $mode, $options, $openedPath);
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
                 * See notes in `->synthesiseIncludeStat(...)`.
                 */
                $streamHandler->synthesiseIncludeStat($effectivePath, strlen($contents));
            }

            $usePath = (bool) ($options & STREAM_USE_PATH);

            if ($usePath && $openedPath) {
                // This is usually done by the original StreamHandler, but when contents are cached
                // we do not return to there.
                $openedPath = $effectivePath;
            }

            return ['resource' => $cacheStream, 'isInclude' => $isInclude];
        }

        // Contents are not yet cached - forward to the wrapped handler,
        // which will also apply any applicable shifts.
        $result = $this->wrappedStreamHandler->streamOpen(
            $streamWrapper,
            $effectivePath,
            $mode,
            $options,
            $openedPath
        );
        $sourceStream = $result['resource'];

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
            $streamHandler->synthesiseIncludeStat($effectivePath, strlen($contents));
        }

        return $result;
    }
}
