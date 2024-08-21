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
     * Fetches the key to use for the realpath cache within the cache pool.
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
     * Fetches the key to use for the stat cache within the cache pool.
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
     * Fetches whether to hook built-in functions such as `clearstatcache(...)`.
     */
    public function shouldHookBuiltinFunctions(): bool;
}
