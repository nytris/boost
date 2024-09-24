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

namespace Nytris\Boost\FsCache\Stat;

use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapperInterface;
use LogicException;
use Nytris\Boost\Environment\EnvironmentInterface;
use Nytris\Boost\FsCache\CanonicaliserInterface;
use Nytris\Boost\FsCache\Realpath\RealpathCacheInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class StatCache.
 *
 * Caches filesystem stats, optionally also to a PSR cache implementation,
 * to improve performance.
 *
 * @phpstan-import-type StatCacheEntry from StatCacheInterface
 * @author Dan Phillimore <dan@ovms.co>
 */
class StatCache implements StatCacheInterface
{
    private int $startTime;

    public function __construct(
        private readonly StreamHandlerInterface $wrappedStreamHandler,
        EnvironmentInterface $environment,
        private readonly CanonicaliserInterface $canonicaliser,
        private readonly RealpathCacheInterface $realpathCache,
        private readonly CacheItemPoolInterface $statCachePool,
        private readonly bool $asVirtualFilesystem
    ) {
        $this->startTime = (int) $environment->getStartTime();
    }

    /**
     * Deletes both the include and non-include stats
     * for the specified realpath in the PSR backing cache.
     */
    private function deleteBackingCacheEntry(string $realpath): void
    {
        $this->statCachePool->deleteItem($this->getBackingCacheItemKey($realpath, isInclude: true));
        $this->statCachePool->deleteItem($this->getBackingCacheItemKey($realpath, isInclude: false));
    }

    /**
     * Fetches the stat for the given realpath from the PSR backing cache, if any.
     *
     * @param string $realpath
     * @param bool $isInclude
     * @return array<mixed>|null
     */
    private function getBackingCacheEntry(string $realpath, bool $isInclude): ?array
    {
        $statCacheItem = $this->getBackingCacheItem($realpath, $isInclude);

        return $statCacheItem->isHit() ? $statCacheItem->get() : null;
    }

    /**
     * Fetches the PSR backing cache item for the given realpath's stat.
     */
    private function getBackingCacheItem(string $realpath, bool $isInclude): CacheItemInterface
    {
        return $this->statCachePool->getItem(
            $this->getBackingCacheItemKey($realpath, isInclude: $isInclude)
        );
    }

    /**
     * Fetches the key to use for the PSR backing cache item for the given realpath.
     */
    private function getBackingCacheItemKey(string $realpath, bool $isInclude): string
    {
        return ($isInclude ? 'include_' : 'plain_') .
            $this->canonicaliser->canonicaliseCacheKey($realpath);
    }

    /**
     * @inheritDoc
     */
    public function getCachedStat(
        string $path,
        bool $isLinkStat,
        bool $isInclude,
        bool &$accessible = true,
        string &$linkPath = null
    ): ?array {
        // Link status fetches (lstat()s) stat the symlink file itself (if one exists at the given path)
        // vs. stat() which stats the eventual file that the symlink points to.
        $linkPath = $this->realpathCache->getCachedEventualPath(
            $path,
            followSymlinks: !$isLinkStat,
            accessible: $accessible
        );

        if (!$accessible) {
            // File is explicitly cached as non-existent.
            return null;
        }

        $stat = $this->getBackingCacheEntry($linkPath, isInclude: $isInclude);

        if ($stat !== null) {
            // Stat is already cached: just return it.
            $accessible = true;

            return $stat;
        }

        // Not cached; we have no way of knowing whether it is accessible,
        // so we just assume it is for now until resolved by the backing store.
        $accessible = true;

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getPathStat(string $path, bool $isLinkStat, bool $quiet = true): ?array
    {
        $accessible = true;
        /** @var string|null $linkPath */
        $linkPath = null;
        $stat = $this->getCachedStat(
            $path,
            isLinkStat: $isLinkStat,
            isInclude: false,
            accessible: $accessible,
            linkPath: $linkPath
        );

        if ($stat !== null) {
            return $stat;
        }

        if (!$accessible) {
            // Path has been cached as non-accessible.
            return null;
        }

        if ($this->asVirtualFilesystem) {
            // The stat should not hit the backing store.
            return null;
        }

        $stat = $this->wrappedStreamHandler->urlStat(
            $path,
            flags: ($isLinkStat ? STREAM_URL_STAT_LINK : 0) | ($quiet ? STREAM_URL_STAT_QUIET : 0)
        );

        if ($stat === false) {
            // Stat failed.
            return null;
        }

        // Cache stat for future reference.
        $this->setBackingCacheEntry($linkPath, isInclude: false, entry: $this->statToSynthetic($stat));

        return $stat;
    }

    /**
     * @inheritDoc
     */
    public function getStreamStat(StreamWrapperInterface $streamWrapper): ?array
    {
        /** @var string|null $linkPath */
        $linkPath = null;
        $stat = $this->getCachedStat(
            $streamWrapper->getOpenPath(),
            isLinkStat: false,
            isInclude: $streamWrapper->isInclude(),
            linkPath: $linkPath
        );

        if ($stat !== null) {
            // Stat is already cached: just return it.
            return $stat;
        }

        if ($this->asVirtualFilesystem) {
            // The stat should not hit the backing store.
            return null;
        }

        $stat = $this->wrappedStreamHandler->streamStat($streamWrapper);

        if ($stat === false) {
            // Stat failed.
            return null;
        }

        // Cache stat for future reference.
        $this->setBackingCacheEntry(
            $linkPath,
            isInclude: $streamWrapper->isInclude(),
            entry: $this->statToSynthetic($stat)
        );

        return $stat;
    }

    /**
     * @inheritDoc
     */
    public function invalidate(): void
    {
        $this->statCachePool->clear();
    }

    /**
     * @inheritDoc
     */
    public function invalidatePath(string $path): void
    {
        // Clear the canonical path entries.
        $canonicalPath = $this->canonicaliser->canonicalise($path);
        $this->deleteBackingCacheEntry($canonicalPath);

        // Clear the eventual path entries if not cleared above.
        $eventualPath = $this->realpathCache->getCachedEventualPath($path);
        $this->deleteBackingCacheEntry($eventualPath);
    }

    /**
     * @inheritDoc
     */
    public function isDirectory(string $path): bool
    {
        $stat = $this->getCachedStat($path, isLinkStat: false, isInclude: false);

        return $stat !== null && ($stat['mode'] & 0040000);
    }

    /**
     * @inheritDoc
     */
    public function persistStatCache(): void
    {
        $this->statCachePool->commit();
    }

    /**
     * @inheritDoc
     */
    public function populateStatWithSize(
        string $realpath,
        int $size,
        bool $isInclude
    ): void {
        $stat = $this->getBackingCacheEntry($realpath, isInclude: $isInclude);

        if ($stat !== null) {
            // Already cached - just update the size if needed.
            $stat['size'] = $size;
            $stat[7] = $size;
            $this->setBackingCacheEntry($realpath, isInclude: $isInclude, entry: $stat);

            return;
        }

        // Note that we cannot stat the open stream, as it may be a `php://memory` stream
        // rather than the original, e.g. if it was shifted.
        // This may be served from cache if previously performed by a non-include stat.
        $nonIncludeStat = $this->getPathStat($realpath, isLinkStat: false);

        if ($nonIncludeStat === null) {
            if (!$this->asVirtualFilesystem) {
                throw new LogicException('Cannot stat original realpath "' . $realpath . '"');
            }

            $this->synthesiseStat(
                $realpath,
                isInclude: $isInclude,
                isDir: false,
                mode: 0777,
                // Ensure we use the size of the potentially shifted contents and not the original.
                size: $size
            );

            return;
        }

        $includeStat = $this->statToSynthetic([...$nonIncludeStat, 'size' => $size]);

        $this->setBackingCacheEntry($realpath, isInclude: $isInclude, entry: $includeStat);
    }

    /**
     * Stores the given entry for the specified realpath in the PSR backing cache.
     *
     * @param string $realpath
     * @param bool $isInclude
     * @param array<mixed> $entry
     */
    private function setBackingCacheEntry(string $realpath, bool $isInclude, array $entry): void
    {
        $statCacheItem = $this->getBackingCacheItem($realpath, $isInclude);

        $statCacheItem->set($entry);
        $this->statCachePool->saveDeferred($statCacheItem);
    }

    /**
     * @inheritDoc
     */
    public function setStat(string $realpath, array $stat, bool $isInclude): void
    {
        $this->setBackingCacheEntry($realpath, isInclude: $isInclude, entry: $stat);
    }

    /**
     * @inheritDoc
     */
    public function statToSynthetic(array $stat): array
    {
        $syntheticStat = [
            'dev' => 0,
            'ino' => 0,
            'mode' => $stat['mode'],
            'nlink' => 0,
            'uid' => $stat['uid'],
            'gid' => $stat['gid'],
            'rdev' => 0,
            'size' => $stat['size'],
            'atime' => $stat['atime'],
            'mtime' => $stat['mtime'],
            'ctime' => $stat['ctime'],
            'blksize' => -1,
            'blocks' => -1,
        ];

        // Stat elements are also available in the above order under indexed keys.
        return array_merge(array_values($syntheticStat), $syntheticStat);
    }

    /**
     * @inheritDoc
     */
    public function synthesiseStat(
        string $realpath,
        bool $isInclude,
        bool $isDir,
        int $mode,
        int $size
    ): void {
        if (!$this->asVirtualFilesystem) {
            throw new LogicException(
                'Cannot use fully synthetic stats outside of a virtual filesystem'
            );
        }

        $item = $this->getBackingCacheItem($realpath, isInclude: $isInclude);

        if ($item->isHit()) {
            throw new LogicException('Synthetic stat already exists for path "' . $realpath . '"');
        }

        $stat = $this->statToSynthetic([
            'mode' => ($isDir ? 0040000 : 0100000) | 0777,
            'uid' => 0,
            'gid' => 0,
            // Ensure we use a past timestamp to ensure PHP files are cached
            // when `opcache.file_update_protection` is enabled.
            'atime' => $this->startTime - 10,
            'mtime' => $this->startTime - 10,
            'ctime' => $this->startTime - 10,
            'size' => $size,
        ]);

        $this->setBackingCacheEntry($realpath, isInclude: $isInclude, entry: $stat);
    }

    /**
     * @inheritDoc
     */
    public function updateSyntheticStat(
        string $realpath,
        bool $isInclude,
        ?int $mode = null,
        ?int $size = null,
        ?int $modificationTime = null,
        ?int $accessTime = null,
        ?int $uid = null,
        ?int $gid = null
    ): void {
        if (!$this->asVirtualFilesystem) {
            throw new LogicException(
                'Cannot use fully synthetic stats outside of a virtual filesystem'
            );
        }

        $stat = $this->getBackingCacheEntry($realpath, isInclude: $isInclude);

        if ($stat === null) {
            throw new LogicException('No synthetic stat exists for path "' . $realpath . '"');
        }

        // Update the stat components as needed.

        if ($size !== null) {
            $stat['size'] = $size;
            $stat[7] = $size;
        }

        if ($mode !== null) {
            // Preserve the upper file type bits of the full mode.
            $fullMode = ($stat['mode'] & 0777000) | ($mode & 0777);

            $stat['mode'] = $fullMode;
            $stat[2] = $fullMode;
        }

        if ($uid !== null) {
            $stat['uid'] = $uid;
            $stat[4] = $uid;
        }

        if ($gid !== null) {
            $stat['gid'] = $gid;
            $stat[5] = $gid;
        }

        if ($accessTime !== null) {
            $stat['atime'] = $accessTime;
            $stat[8] = $accessTime;
        }

        if ($modificationTime !== null) {
            $stat['mtime'] = $modificationTime;
            $stat[9] = $modificationTime;
        }

        $this->setBackingCacheEntry($realpath, isInclude: $isInclude, entry: $stat);
    }
}
