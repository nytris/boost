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

namespace Nytris\Boost\FsCache\Directory\Enumerator;

use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapperInterface;
use Nytris\Boost\FsCache\Directory\Directory\DirectoryInterface;
use Nytris\Boost\FsCache\Directory\DirectoryCacheInterface;
use WeakMap;

/**
 * Class DirectoryEnumerator.
 *
 * Enumerates directory entries.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class DirectoryEnumerator implements DirectoryEnumeratorInterface
{
    /**
     * @var WeakMap<StreamWrapperInterface, DirectoryInterface>
     */
    private WeakMap $streamWrapperToDirectoryMap;

    public function __construct(
        private readonly StreamHandlerInterface $wrappedStreamHandler,
        private readonly ?DirectoryCacheInterface $directoryCache,
        private readonly bool $asVirtualFilesystem
    ) {
        $this->streamWrapperToDirectoryMap = new WeakMap();
    }

    /**
     * @inheritDoc
     */
    public function closeDirectory(StreamWrapperInterface $streamWrapper): bool
    {
        $success = $this->streamWrapperToDirectoryMap[$streamWrapper]->close($streamWrapper);

        unset($this->streamWrapperToDirectoryMap[$streamWrapper]);

        return $success;
    }

    /**
     * @inheritDoc
     */
    public function openDirectory(StreamWrapperInterface $streamWrapper, string $path, int $options)
    {
        if ($this->directoryCache === null) {
            if ($this->asVirtualFilesystem) {
                // Directory entries access is impossible in virtual filesystem mode with no directory cache,
                // as there is nowhere to store or retrieve directory entries from.
                return null;
            }

            // Directory cache is disabled, so ignore.
            return $this->wrappedStreamHandler->openDir($streamWrapper, $path, $options);
        }

        $directory = $this->directoryCache->getItemForPath($path, $streamWrapper, $this->wrappedStreamHandler);

        if ($directory->isCached()) {
            // Create a fake directory handle resource to attach the entries to.
            $directoryPlaceholderResource = fopen('php://memory', 'rb+');

            $this->streamWrapperToDirectoryMap[$streamWrapper] = $directory;

            return $directoryPlaceholderResource;
        }

        if ($this->asVirtualFilesystem) {
            // Directory does not exist in virtual filesystem.
            return null;
        }

        // Directory cache is enabled, but missed.
        $directoryHandle = $this->wrappedStreamHandler->openDir($streamWrapper, $path, $options);

        $this->streamWrapperToDirectoryMap[$streamWrapper] = $directory;

        return $directoryHandle;
    }

    /**
     * @inheritDoc
     */
    public function readDirectory(StreamWrapperInterface $streamWrapper)
    {
        return $this->streamWrapperToDirectoryMap[$streamWrapper]
            ->getNextEntry($streamWrapper, $this->wrappedStreamHandler);
    }
}
