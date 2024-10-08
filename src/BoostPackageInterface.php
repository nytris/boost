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

namespace Nytris\Boost;

use Asmblah\PhpCodeShift\Shifter\Filter\FileFilterInterface;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Core\Package\PackageInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Interface BoostPackageInterface.
 *
 * Configures the installation of Nytris Boost.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface BoostPackageInterface extends PackageInterface
{
    /**
     * Fetches the cache in which to store file contents, or null when disabled.
     */
    public function getContentsCache(string $boostCachePath): ?ContentsCacheInterface;

    /**
     * Fetches the filter for which files to hook built-in functions
     * such as `clearstatcache(...)`. for, or null to disable.
     */
    public function getHookBuiltinFunctionsFilter(): ?FileFilterInterface;

    /**
     * Fetches the filter for which file paths to cache.
     */
    public function getPathFilter(): FileFilterInterface;

    /**
     * Fetches the key to use for the realpath cache within the cache pool.
     *
     * @deprecated Unused - use the cache pool namespace.
     */
    public function getRealpathCacheKey(): string;

    /**
     * Fetches the PSR cache pool to use for the realpath cache.
     *
     * Set to null to disable PSR cache persistence.
     * Cache will still be maintained for the life of the request/CLI process.
     */
    public function getRealpathCachePool(string $boostCachePath): ?CacheItemPoolInterface;

    /**
     * Fetches the read-only PSR cache pool to use for preloading the realpath cache.
     *
     * Set to null to disable preloading from a PSR cache.
     */
    public function getRealpathPreloadCachePool(string $boostCachePath): ?CacheItemPoolInterface;

    /**
     * Fetches the key to use for the stat cache within the cache pool.
     *
     * @deprecated Unused - use the cache pool namespace.
     */
    public function getStatCacheKey(): string;

    /**
     * Fetches the PSR cache pool to use for the stat cache.
     *
     * Set to null to disable PSR cache persistence.
     * Cache will still be maintained for the life of the request/CLI process.
     */
    public function getStatCachePool(string $boostCachePath): ?CacheItemPoolInterface;

    /**
     * Fetches the read-only PSR cache pool to use for preloading the stat cache.
     *
     * Set to null to disable preloading from a PSR cache.
     */
    public function getStatPreloadCachePool(string $boostCachePath): ?CacheItemPoolInterface;

    /**
     * In virtual-filesystem mode, the cache is write-allocate with no write-through
     * to the next stream handler in the chain (usually the original one, which persists to disk).
     */
    public function isVirtualFilesystem(): bool;

    /**
     * Fetches whether the non-existence of files should be cached in the realpath cache.
     */
    public function shouldCacheNonExistentFiles(): bool;
}
