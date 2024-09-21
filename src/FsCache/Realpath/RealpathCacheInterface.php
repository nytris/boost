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

namespace Nytris\Boost\FsCache\Realpath;

/**
 * Interface RealpathCacheInterface.
 *
 * Caches realpaths, optionally also to a PSR cache implementation, to improve performance.
 *
 * @phpstan-type RealpathCacheStorage array<string, RealpathCacheEntry>
 * @phpstan-type RealpathCacheEntry array{canonical?: string, exists?: bool, realpath?: string, symlink?: string}
 * @author Dan Phillimore <dan@ovms.co>
 */
interface RealpathCacheInterface
{
    /**
     * Adds the given path (and all segments of the realpath) to the realpath cache.
     */
    public function cacheRealpath(string $canonicalPath, string $realpath): void;

    /**
     * Fetches the realpath for the given path if cached, even if its existence is not known.
     */
    public function getCachedEventualPath(
        string $path,
        bool $followSymlinks = true,
        bool &$accessible = true
    ): string;

    /**
     * Fetches the realpath for the given path, even if it does not exist.
     */
    public function getEventualPath(
        string $path,
        bool $followSymlinks = true,
        bool &$accessible = true
    ): string;

    /**
     * Fetches the realpath for the given path if cached,
     * otherwise resolves and caches it.
     */
    public function getRealpath(
        string $path,
        bool $getEventual = false,
        bool $followSymlinks = true,
        bool &$accessible = true
    ): ?string;

    /**
     * Fetches the realpath cache entry for the given path if cached,
     * or null otherwise.
     *
     * @param string $path
     * @param bool $followSymlinks
     * @return RealpathCacheEntry|null
     */
    public function getRealpathCacheEntry(string $path, bool $followSymlinks): ?array;

    /**
     * Clears the realpath cache.
     */
    public function invalidate(): void;

    /**
     * Clears the realpath cache for the given path.
     */
    public function invalidatePath(string $path): void;

    /**
     * Persists the current realpath cache via configured PSR cache.
     */
    public function persistRealpathCache(): void;
}
