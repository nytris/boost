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

namespace Nytris\Boost\FsCache\Stream\Handler;

use Asmblah\PhpCodeShift\Shifter\Filter\FileFilterInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Handler\AbstractStreamHandlerDecorator;
use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapperInterface;
use LogicException;
use Nytris\Boost\Environment\EnvironmentInterface;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\Realpath\RealpathCacheInterface;
use Nytris\Boost\FsCache\Stat\StatCacheInterface;
use Nytris\Boost\FsCache\Stream\Opener\StreamOpenerInterface;

/**
 * Class FsCachingStreamHandler.
 *
 * Caches realpath and filesystem stats, optionally also to a PSR cache implementation,
 * to improve performance.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class FsCachingStreamHandler extends AbstractStreamHandlerDecorator implements FsCachingStreamHandlerInterface
{
    public function __construct(
        StreamHandlerInterface $wrappedStreamHandler,
        private readonly EnvironmentInterface $environment,
        private readonly StreamOpenerInterface $streamOpener,
        private readonly RealpathCacheInterface $realpathCache,
        private readonly StatCacheInterface $statCache,
        private readonly ?ContentsCacheInterface $contentsCache,
        private readonly FileFilterInterface $pathFilter,
        private readonly bool $asVirtualFilesystem
    ) {
        parent::__construct($wrappedStreamHandler);
    }

    /**
     * @inheritDoc
     */
    public function cacheRealpath(string $canonicalPath, string $realpath): void
    {
        $this->realpathCache->cacheRealpath($canonicalPath, $realpath);
    }

    /**
     * @inheritDoc
     */
    public function getEventualPath(string $path): string
    {
        return $this->realpathCache->getEventualPath($path);
    }

    /**
     * @inheritDoc
     */
    public function getInMemoryRealpathEntryCache(): array
    {
        return $this->realpathCache->getInMemoryEntryCache();
    }

    /**
     * @inheritDoc
     */
    public function getInMemoryStatEntryCache(): array
    {
        return $this->statCache->getInMemoryEntryCache();
    }

    /**
     * @inheritDoc
     */
    public function getRealpath(string $path): ?string
    {
        return $this->realpathCache->getRealpath($path);
    }

    /**
     * @inheritDoc
     */
    public function invalidateCaches(): void
    {
        if ($this->asVirtualFilesystem) {
            // In virtual filesystem mode, the caches are the filesystem storage so should not be invalidated.
            return;
        }

        $this->realpathCache->invalidate();
        $this->statCache->invalidate();
    }

    /**
     * @inheritDoc
     */
    public function invalidatePath(string $path): void
    {
        if ($this->asVirtualFilesystem) {
            // In virtual filesystem mode, the caches are the filesystem storage so should not be invalidated.
            return;
        }

        $eventualPath = $this->realpathCache->getCachedEventualPath($path);

        if (!$this->pathFilter->fileMatches($eventualPath)) {
            // Path is excluded from cache, so ignore.
            return;
        }

        // Clear stat cache first, as it may need the realpath cache
        // in order to resolve paths to clear in the stat cache.
        $this->statCache->invalidatePath($path);
        $this->realpathCache->invalidatePath($path);
        $this->contentsCache?->invalidatePath($path);
    }

    /**
     * @inheritDoc
     */
    public function mkdir(StreamWrapperInterface $streamWrapper, string $path, int $mode, int $options): bool
    {
        $eventualPath = $this->realpathCache->getEventualPath($path);

        if (!$this->pathFilter->fileMatches($eventualPath)) {
            // Path is excluded from cache, so ignore.
            return $this->wrappedStreamHandler->mkdir($streamWrapper, $path, $mode, $options);
        }

        if (!$this->asVirtualFilesystem) {
            // Filesystem cache mode: just invalidate this path and forward to the next handler.
            $this->invalidatePath($path);

            return $this->wrappedStreamHandler->mkdir($streamWrapper, $path, $mode, $options);
        }

        $isRecursive = $options & STREAM_MKDIR_RECURSIVE;

        if ($isRecursive) {
            $createDirectory = function (string $path) use (&$createDirectory, $mode): bool {
                if ($path === DIRECTORY_SEPARATOR) {
                    return true; // Root directory always exists.
                }

                if ($this->statCache->isDirectory($path)) {
                    return true;
                }

                if (!$createDirectory(dirname($path))) {
                    return false;
                }

                try {
                    $this->statCache->synthesiseStat(
                        $path,
                        isInclude: false,
                        isDir: true,
                        mode: $mode,
                        size: 0
                    );
                } catch (LogicException) {
                    return false;
                }

                return true;
            };

            return $createDirectory($eventualPath);
        }

        if (!$this->statCache->isDirectory(dirname($eventualPath))) {
            // In non-recursive mode, parent directory needs to exist.
            return false;
        }

        try {
            $this->statCache->synthesiseStat(
                $eventualPath,
                isInclude: false,
                isDir: true,
                mode: $mode,
                size: 0
            );
        } catch (LogicException) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function openDir(StreamWrapperInterface $streamWrapper, string $path, int $options)
    {
        $eventualPath = $this->realpathCache->getEventualPath($path);

        if (!$this->pathFilter->fileMatches($eventualPath)) {
            // Path is excluded from cache, so ignore.
            return $this->wrappedStreamHandler->openDir($streamWrapper, $path, $options);
        }

        if (!$this->asVirtualFilesystem) {
            // Filesystem cache mode: just forward to the next handler for now.
            // TODO: Use cache for directory structure too.
            return $this->wrappedStreamHandler->openDir($streamWrapper, $path, $options);
        }

        throw new LogicException(
            sprintf(
                'Virtual filesystem does not yet support opening directory "%s" for enumeration',
                $eventualPath
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function persistRealpathCache(): void
    {
        $this->realpathCache->persistRealpathCache();
    }

    /**
     * @inheritDoc
     */
    public function persistStatCache(): void
    {
        $this->statCache->persistStatCache();
    }

    /**
     * @inheritDoc
     */
    public function rename(StreamWrapperInterface $streamWrapper, string $fromPath, string $toPath): bool
    {
        $eventualFromPath = $this->realpathCache->getEventualPath($fromPath);
        $eventualToPath = $this->realpathCache->getEventualPath($toPath);

        $fromPathMatchesFilter = $this->pathFilter->fileMatches($eventualFromPath);
        $toPathMatchesFilter = $this->pathFilter->fileMatches($eventualToPath);

        if (!$fromPathMatchesFilter && !$toPathMatchesFilter) {
            // Both paths are excluded from cache, so ignore.
            return $this->wrappedStreamHandler->rename($streamWrapper, $fromPath, $toPath);
        }

        if ($this->asVirtualFilesystem) {
            $this->realpathCache->invalidatePath($fromPath);
            $this->realpathCache->invalidatePath($toPath);

            $nonIncludeStat = $this->statCache->getCachedStat(
                $fromPath,
                isLinkStat: true,
                isInclude: false
            );
            $includeStat = $this->statCache->getCachedStat(
                $fromPath,
                isLinkStat: true,
                isInclude: true
            );
            $this->statCache->invalidatePath($fromPath);

            if ($nonIncludeStat !== null) {
                $this->statCache->setStat($toPath, $nonIncludeStat, isInclude: false);
            }

            if ($includeStat !== null) {
                $this->statCache->setStat($toPath, $includeStat, isInclude: true);
            }

            $nonIncludeFromItem = $this->contentsCache->getItemForPath($fromPath, isInclude: false);
            $includeFromItem = $this->contentsCache->getItemForPath($fromPath, isInclude: true);

            if ($nonIncludeFromItem->isCached()) {
                $nonIncludeToItem = $this->contentsCache->getItemForPath($toPath, isInclude: false);

                $nonIncludeToItem->setContents($nonIncludeFromItem->getContents());
            }

            if ($includeFromItem->isCached()) {
                $includeToItem = $this->contentsCache->getItemForPath($toPath, isInclude: true);

                $includeToItem->setContents($includeFromItem->getContents());
            }

            $this->contentsCache->invalidatePath($fromPath);

            return true;
        }

        $this->invalidatePath($fromPath);
        $this->invalidatePath($toPath);

        return $this->wrappedStreamHandler->rename($streamWrapper, $fromPath, $toPath);
    }

    /**
     * @inheritDoc
     */
    public function rmdir(StreamWrapperInterface $streamWrapper, string $path, int $options): bool
    {
        $eventualPath = $this->realpathCache->getEventualPath($path);

        if (!$this->pathFilter->fileMatches($eventualPath)) {
            // Path is excluded from cache, so ignore.
            return $this->wrappedStreamHandler->rmdir($streamWrapper, $path, $options);
        }

        if ($this->asVirtualFilesystem) {
            // TODO: Remove directory from realpath & stat caches (stat entry mode will indicate a dir).
            throw new LogicException('Nytris Boost :: rmdir() in virtual FS mode not yet supported');
        }

        $this->invalidatePath($path);

        return $this->wrappedStreamHandler->rmdir($streamWrapper, $path, $options);
    }

    /**
     * @inheritDoc
     */
    public function streamFlush(StreamWrapperInterface $streamWrapper): bool
    {
        return $this->streamOpener->flushStream($streamWrapper);
    }

    /**
     * @inheritDoc
     */
    public function streamLock(StreamWrapperInterface $streamWrapper, int $operation): bool
    {
        if ($this->asVirtualFilesystem) {
            // Locking is not (yet) supported.
            return true;
        }

        return $this->wrappedStreamHandler->streamLock($streamWrapper, $operation);
    }

    /**
     * @inheritDoc
     */
    public function streamMetadata(string $path, int $option, mixed $value): bool
    {
        $eventualPath = $this->realpathCache->getEventualPath($path);

        if (!$this->pathFilter->fileMatches($eventualPath)) {
            // Path is excluded from cache, so ignore.
            return $this->wrappedStreamHandler->streamMetadata($path, $option, $value);
        }

        if (!$this->asVirtualFilesystem) {
            // Not in virtual filesystem mode - a change is likely being made (owner, group etc.)
            // so clear the caches for this path and then forward to the next handler.
            $this->invalidatePath($path);

            return $this->wrappedStreamHandler->streamMetadata($path, $option, $value);
        }

        try {
            switch ($option) {
                case STREAM_META_TOUCH:
                    $modificationTime = $value[0] ?? (int) $this->environment->getTime();
                    $accessTime = $value[1] ?? $modificationTime;

                    if (!$this->statCache->isPathCachedAsExistent($eventualPath)) {
                        $this->statCache->synthesiseStat(
                            $eventualPath,
                            isInclude: false,
                            isDir: false,
                            mode: 0666, // TODO: Apply umask?
                            size: 0
                        );
                    }

                    $this->statCache->updateSyntheticStat(
                        $eventualPath,
                        isInclude: false,
                        modificationTime: $modificationTime,
                        accessTime: $accessTime
                    );

                    return true;
                case STREAM_META_OWNER_NAME:
                    $userId = $this->environment->getUserIdFromName($value);

                    if ($userId === null) {
                        return false; // Cannot resolve username to an ID.
                    }

                    $this->statCache->updateSyntheticStat(
                        $eventualPath,
                        isInclude: false,
                        uid: $userId
                    );

                    return true;
                case STREAM_META_OWNER:
                    $this->statCache->updateSyntheticStat(
                        $eventualPath,
                        isInclude: false,
                        uid: $value
                    );

                    return true;
                case STREAM_META_GROUP_NAME:
                    $groupId = $this->environment->getGroupIdFromName($value);

                    if ($groupId === null) {
                        return false; // Cannot resolve group name to an ID.
                    }

                    $this->statCache->updateSyntheticStat(
                        $eventualPath,
                        isInclude: false,
                        gid: $groupId
                    );

                    return true;
                case STREAM_META_GROUP:
                    $this->statCache->updateSyntheticStat(
                        $eventualPath,
                        isInclude: false,
                        gid: $value
                    );

                    return true;
                case STREAM_META_ACCESS:
                    $this->statCache->updateSyntheticStat(
                        $eventualPath,
                        isInclude: false,
                        mode: $value
                    );

                    return true;
                default:
                    // Unsupported or unknown metadata.
                    return false;
            }
        } catch (LogicException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function streamOpen(
        StreamWrapperInterface $streamWrapper,
        string $path,
        string $mode,
        int $options,
        ?string &$openedPath
    ): ?array {
        $eventualPath = $this->realpathCache->getEventualPath($path);

        if (!$this->pathFilter->fileMatches($eventualPath)) {
            // Path is excluded from cache, so ignore.
            return $this->wrappedStreamHandler->streamOpen(
                $streamWrapper,
                $path,
                $mode,
                $options,
                $openedPath
            );
        }

        return $this->streamOpener->openStream(
            $streamWrapper,
            $eventualPath,
            $mode,
            $options,
            $openedPath
        );
    }

    /**
     * @inheritDoc
     */
    public function streamStat(StreamWrapperInterface $streamWrapper): array|false
    {
        $eventualPath = $this->realpathCache->getEventualPath($streamWrapper->getOpenPath());

        if (!$this->pathFilter->fileMatches($eventualPath)) {
            // Path is excluded from cache, so ignore.
            return $this->wrappedStreamHandler->streamStat($streamWrapper);
        }

        return $this->statCache->getStreamStat($streamWrapper) ?? false;
    }

    /**
     * @inheritDoc
     */
    public function unlink(StreamWrapperInterface $streamWrapper, string $path): bool
    {
        $eventualPath = $this->realpathCache->getEventualPath($path);

        if (!$this->pathFilter->fileMatches($eventualPath)) {
            // Path is excluded from cache, so ignore.
            return $this->wrappedStreamHandler->unlink($streamWrapper, $path);
        }

        if ($this->asVirtualFilesystem) {
            $this->statCache->invalidatePath($eventualPath);
            $this->contentsCache->invalidatePath($eventualPath);
            $this->realpathCache->invalidatePath($eventualPath);

            return true;
        }

        $this->invalidatePath($path);

        return $this->wrappedStreamHandler->unlink($streamWrapper, $path);
    }

    /**
     * @inheritDoc
     */
    public function urlStat(string $path, int $flags): array|false
    {
        $eventualPath = $this->realpathCache->getEventualPath($path);

        if (!$this->pathFilter->fileMatches($eventualPath)) {
            // Path is excluded from cache, so ignore.
            return $this->wrappedStreamHandler->urlStat($path, $flags);
        }

        // Use lstat(...) for links but stat() for other files.
        $isLinkStat = (bool) ($flags & STREAM_URL_STAT_LINK);

        return $this->statCache->getPathStat(
            $path,
            isLinkStat: $isLinkStat,
            quiet: (bool) ($flags & STREAM_URL_STAT_QUIET)
        ) ?? false;
    }
}
