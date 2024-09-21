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
 * @phpstan-import-type StatCacheStorage from StatCacheInterface
 * @phpstan-import-type StatCacheEntry from StatCacheInterface
 * @author Dan Phillimore <dan@ovms.co>
 */
class StatCache implements StatCacheInterface
{
    private int $startTime;
    /**
     * @var StatCacheStorage
     */
    private array $statCacheForIncludes = [];
    /**
     * @var StatCacheStorage
     */
    private array $statCacheForNonIncludes = [];
    private bool $statCacheIsDirty = false;
    private ?CacheItemInterface $statCachePoolItemForIncludes = null;
    private ?CacheItemInterface $statCachePoolItemForNonIncludes = null;

    public function __construct(
        private readonly StreamHandlerInterface $wrappedStreamHandler,
        EnvironmentInterface $environment,
        private readonly CanonicaliserInterface $canonicaliser,
        private readonly RealpathCacheInterface $realpathCache,
        private readonly ?CacheItemPoolInterface $statCachePool,
        string $statCacheKey,
        private readonly bool $asVirtualFilesystem
    ) {
        $this->startTime = (int) $environment->getStartTime();

        // Load the stat cache from the PSR cache if enabled.
        if ($this->statCachePool !== null) {
            $this->statCachePoolItemForIncludes = $this->statCachePool->getItem($statCacheKey . '_includes');

            if ($this->statCachePoolItemForIncludes->isHit()) {
                $this->statCacheForIncludes = $this->statCachePoolItemForIncludes->get();
            }

            $this->statCachePoolItemForNonIncludes = $this->statCachePool->getItem($statCacheKey . '_plain');

            if ($this->statCachePoolItemForNonIncludes->isHit()) {
                $this->statCacheForNonIncludes = $this->statCachePoolItemForNonIncludes->get();
            }
        }
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

        if ($isInclude) {
            $effectiveStatCache =& $this->statCacheForIncludes;
        } else {
            $effectiveStatCache =& $this->statCacheForNonIncludes;
        }

        if (array_key_exists($linkPath, $effectiveStatCache)) {
            // Stat is already cached: just return it.
            $accessible = true;

            return $effectiveStatCache[$linkPath];
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
        $this->statCacheForNonIncludes[$linkPath] = $this->statToSynthetic($stat);
        $this->statCacheIsDirty = true;

        return $stat;
    }

    /**
     * @inheritDoc
     */
    public function getStreamStat(StreamWrapperInterface $streamWrapper): ?array
    {
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

        if ($streamWrapper->isInclude()) {
            $effectiveStatCache =& $this->statCacheForIncludes;
        } else {
            $effectiveStatCache =& $this->statCacheForNonIncludes;
        }

        // Cache stat for future reference.
        $effectiveStatCache[$linkPath] = $this->statToSynthetic($stat);
        $this->statCacheIsDirty = true;

        return $stat;
    }

    /**
     * @inheritDoc
     */
    public function invalidate(): void
    {
        $this->statCacheForIncludes = [];
        $this->statCacheForNonIncludes = [];

        $this->statCacheIsDirty = true;
    }

    /**
     * @inheritDoc
     */
    public function invalidatePath(string $path): void
    {
        // Clear the canonical path entries.
        $canonicalPath = $this->canonicaliser->canonicalise($path);

        unset(
            $this->statCacheForIncludes[$canonicalPath],
            $this->statCacheForNonIncludes[$canonicalPath]
        );

        // Clear the eventual path entries if not cleared above.
        $eventualPath = $this->realpathCache->getCachedEventualPath($path);
        unset(
            $this->statCacheForIncludes[$eventualPath],
            $this->statCacheForNonIncludes[$eventualPath]
        );

        $this->statCacheIsDirty = true;
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
        if ($this->statCachePoolItemForIncludes === null || $this->statCacheIsDirty === false) {
            return; // Persistence is disabled or nothing changed; nothing to do.
        }

        $this->statCachePoolItemForIncludes->set($this->statCacheForIncludes);
        $this->statCachePoolItemForNonIncludes->set($this->statCacheForNonIncludes);
        $this->statCachePool->saveDeferred($this->statCachePoolItemForIncludes);
        $this->statCachePool->saveDeferred($this->statCachePoolItemForNonIncludes);
    }

    /**
     * @inheritDoc
     */
    public function populateStatWithSize(
        string $realpath,
        int $size,
        bool $isInclude
    ): void {
        if ($isInclude) {
            $effectiveStatCache =& $this->statCacheForIncludes;
        } else {
            $effectiveStatCache =& $this->statCacheForNonIncludes;
        }

        if (array_key_exists($realpath, $effectiveStatCache)) {
            // Already cached - just update the size if needed.
            $effectiveStatCache[$realpath]['size'] = $size;
            $effectiveStatCache[$realpath][7] = $size;

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

        $effectiveStatCache[$realpath] = $includeStat;
        $this->statCacheIsDirty = true;
    }

    /**
     * @inheritDoc
     */
    public function setStat(string $realpath, array $stat, bool $isInclude): void
    {
        if ($isInclude) {
            $effectiveStatCache =& $this->statCacheForIncludes;
        } else {
            $effectiveStatCache =& $this->statCacheForNonIncludes;
        }

        $effectiveStatCache[$realpath] = $this->statToSynthetic($stat);

        $this->statCacheIsDirty = true;
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

        if ($isInclude) {
            $effectiveStatCache =& $this->statCacheForIncludes;
        } else {
            $effectiveStatCache =& $this->statCacheForNonIncludes;
        }

        if (array_key_exists($realpath, $effectiveStatCache)) {
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

        $effectiveStatCache[$realpath] = $stat;
        $this->statCacheIsDirty = true;
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

        if ($isInclude) {
            $effectiveStatCache =& $this->statCacheForIncludes;
        } else {
            $effectiveStatCache =& $this->statCacheForNonIncludes;
        }

        if (!array_key_exists($realpath, $effectiveStatCache)) {
            throw new LogicException('No synthetic stat exists for path "' . $realpath . '"');
        }

        // Update the stat components as needed.
        $stat =& $effectiveStatCache[$realpath];

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

        $this->statCacheIsDirty = true;
    }
}
