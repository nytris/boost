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

use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapperInterface;

/**
 * Interface StatCacheInterface.
 *
 * Caches filesystem stats, optionally also to a PSR cache implementation,
 * to improve performance.
 *
 * @phpstan-type StatCacheStorage array<string, StatCacheEntry>
 * @phpstan-type StatCacheEntry array<mixed>
 * @author Dan Phillimore <dan@ovms.co>
 */
interface StatCacheInterface
{
    /**
     * Fetches the (link)stat for the given path, only if already cached.
     *
     * @param string $path
     * @param bool $isLinkStat
     * @param bool $isInclude
     * @param bool $accessible
     * @param string|null $linkPath
     * @return array<mixed>|null
     */
    public function getCachedStat(
        string $path,
        bool $isLinkStat,
        bool $isInclude,
        bool &$accessible = true,
        string &$linkPath = null
    ): ?array;

    /**
     * Fetches the (link)stat for the given path.
     *
     * TODO: Only accept realpaths (perhaps for all these methods), shifting responsibility to the caller?
     *
     * @param string $path
     * @param bool $isLinkStat
     * @param bool $quiet
     * @return array<mixed>|null
     */
    public function getPathStat(string $path, bool $isLinkStat, bool $quiet = true): ?array;

    /**
     * Fetches the (link)stat for the given stream.
     *
     * @param StreamWrapperInterface $streamWrapper
     * @return array<mixed>|null
     */
    public function getStreamStat(StreamWrapperInterface $streamWrapper): ?array;

    /**
     * Clears the stat cache.
     */
    public function invalidate(): void;

    /**
     * Clears the stat cache for the given path.
     */
    public function invalidatePath(string $path): void;

    /**
     * Determines whether the given path is a directory.
     */
    public function isDirectory(string $path): bool;

    /**
     * Persists the current stat cache via configured PSR cache.
     */
    public function persistStatCache(): void;

    /**
     * Populates the stat cache if it hasn't already been for the given realpath.
     *
     * In particular, for include stats we need:
     * - The size to match that of the shifted code (if applicable) and not the original,
     * - Timestamps to match, as OPcache won't cache streams with a modification date
     *   after script start if `opcache.file_update_protection` is enabled.
     */
    public function populateStatWithSize(string $realpath, int $size, bool $isInclude): void;

    /**
     * Stores the given stat for the specified path.
     *
     * @param string $realpath
     * @param array<mixed> $stat
     * @param bool $isInclude
     */
    public function setStat(string $realpath, array $stat, bool $isInclude): void;

    /**
     * Converts the given stat to a synthetic one that is not bound to the original inode.
     * This allows it to be persisted without causing strange issues.
     *
     * @param array<mixed> $stat
     * @return array<mixed>
     */
    public function statToSynthetic(array $stat): array;

    /**
     * Creates a synthetic stat for a virtual filesystem.
     */
    public function synthesiseStat(
        string $realpath,
        bool $isInclude,
        bool $isDir,
        int $mode,
        int $size
    ): void;

    /**
     * Updates a stat previously created with `->synthesiseStat(...)`.
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
    ): void;
}
