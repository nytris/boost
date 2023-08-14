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
 * @author Dan Phillimore <dan@ovms.co>
 */
interface FsCachingStreamHandlerInterface extends StreamHandlerInterface
{
    /**
     * Adds the given path (and all segments of the realpath) to the realpath cache.
     */
    public function cacheRealpath(string $canonicalPath, string $realpath): void;

    /**
     * Adds the given path, which may be to a single segment of a given path, to the realpath cache.
     */
    public function cacheRealpathSegment(string $path, string $realpath): void;

    /**
     * Fetches the realpath for the given path, even if it does not exist.
     */
    public function getEventualPath(string $path): string;

    /**
     * Fetches the realpath for the given path if cached and not expired,
     * otherwise resolves and caches it.
     */
    public function getRealpath(string $path): ?string;

    /**
     * Fetches the realpath cache entry for the given path if cached and not expired,
     * or null otherwise.
     */
    public function getRealpathCacheEntry(string $path): ?array;

    /**
     * Clears both the realpath and stat caches.
     */
    public function invalidateCaches(): void;

    /**
     * Clears both the realpath and stat caches for the given path.
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
}
