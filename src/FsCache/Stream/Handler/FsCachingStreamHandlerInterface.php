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
use Nytris\Boost\FsCache\Realpath\RealpathCacheInterface;
use Nytris\Boost\FsCache\Stat\StatCacheInterface;

/**
 * Interface FsCachingStreamHandlerInterface.
 *
 * Caches realpath and filesystem stats, optionally also to a PSR cache implementation,
 * to improve performance.
 *
 * @phpstan-import-type MultipleStatCacheStorage from StatCacheInterface
 * @phpstan-import-type RealpathCacheStorage from RealpathCacheInterface
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
     * Fetches the in-memory realpath entry cache.
     *
     * @return RealpathCacheStorage
     */
    public function getInMemoryRealpathEntryCache(): array;

    /**
     * Fetches the in-memory stat entry cache.
     *
     * @return MultipleStatCacheStorage
     */
    public function getInMemoryStatEntryCache(): array;

    /**
     * Fetches the realpath for the given path if cached,
     * otherwise resolves and caches it.
     */
    public function getRealpath(string $path): ?string;

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
}
