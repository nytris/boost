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

use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;

/**
 * Interface FsCachingStreamHandlerInterface.
 *
 * Caches realpath and filesystem stats, optionally also to a PSR cache implementation,
 * to improve performance.
 *
 * @phpstan-type RealpathCache array<string, RealpathCacheEntry>
 * @phpstan-type RealpathCacheEntry array{canonical?: string, exists?: bool, realpath?: string, symlink?: string}
 * @phpstan-type StatCache array<string, StatCacheEntry>
 * @phpstan-type StatCacheEntry array<mixed>
 * @author Dan Phillimore <dan@ovms.co>
 */
interface FsCachingStreamHandlerInterface extends StreamHandlerInterface
{
    /**
     * Adds the given path (and all segments of the realpath) to the realpath cache.
     */
    public function cacheRealpath(string $canonicalPath, string $realpath): void;

    /**
     * Fetches the realpath for the given path, even if it does not exist.
     */
    public function getEventualPath(string $path): string;

    /**
     * Fetches the realpath for the given path if cached,
     * otherwise resolves and caches it.
     */
    public function getRealpath(string $path): ?string;

    /**
     * Fetches the realpath cache entry for the given path if cached,
     * or null otherwise.
     *
     * @param string $path
     * @return RealpathCacheEntry|null
     */
    public function getRealpathCacheEntry(string $path): ?array;

    /**
     * Clears the realpath and stat caches.
     *
     * Note that this does not affect the contents cache.
     */
    public function invalidateCaches(): void;

    /**
     * Clears the realpath, stat and contents caches for the given path.
     */
    public function invalidatePath(string $path): void;

    /**
     * Persists the current realpath cache via configured PSR cache.
     */
    public function persistRealpathCache(): void;

    /**
     * Persists the current stat cache via configured PSR cache.
     */
    public function persistStatCache(): void;

    /**
     * Populates the stat cache for an include if it hasn't already been.
     *
     * In particular, we need:
     * - The size to match that of the shifted code (if applicable) and not the original,
     * - Timestamps to match, as OPcache won't cache streams with a modification date
     *   after script start if `opcache.file_update_protection` is enabled.
     */
    public function synthesiseIncludeStat(string $realpath, int $size): void;
}
