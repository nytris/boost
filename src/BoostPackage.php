<?php

/*
 * Nytris Boost.
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/nytris/boost/
 *
 * Released under the MIT license.
 * https://github.com/nytris/boost/raw/main/MIT-LICENSE.txt
 */

declare(strict_types=1);

namespace Nytris\Boost;

use Asmblah\PhpCodeShift\Shifter\Filter\FileFilter;
use Asmblah\PhpCodeShift\Shifter\Filter\FileFilterInterface;
use Closure;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\FsCacheInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class BoostPackage.
 *
 * Configures the installation of Nytris Boost.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class BoostPackage implements BoostPackageInterface
{
    public function __construct(
        /**
         * Cache pool in which to persist the realpath cache.
         *
         * Set to null to disable PSR cache persistence.
         * Cache will still be maintained for the life of the request/CLI process.
         *
         * @var Closure(string): CacheItemPoolInterface
         */
        private readonly ?Closure $realpathCachePoolFactory = null,
        /**
         * Cache pool in which to persist the stat cache.
         *
         * Set to null to disable PSR cache persistence.
         * Cache will still be maintained for the life of the request/CLI process.
         *
         * @var Closure(string): CacheItemPoolInterface
         */
        private readonly ?Closure $statCachePoolFactory = null,
        private readonly string $realpathCacheKey = FsCacheInterface::DEFAULT_REALPATH_CACHE_KEY,
        private readonly string $statCacheKey = FsCacheInterface::DEFAULT_STAT_CACHE_KEY,
        /**
         * Whether to hook built-in functions such as `clearstatcache(...)`.
         */
        private readonly bool $hookBuiltinFunctions = true,
        /**
         * Whether the non-existence of files should be cached in the realpath cache.
         */
        private readonly bool $cacheNonExistentFiles = true,
        /**
         * Cache in which to store file contents.
         *
         * Set to null to disable contents caching.
         *
         * @var Closure(string): ContentsCacheInterface
         */
        private readonly ?Closure $contentsCacheFactory = null,
        /**
         * Filter for which file paths to cache in the realpath, stat and contents caches.
         */
        private readonly FileFilterInterface $pathFilter = new FileFilter('**')
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getContentsCache(string $boostCachePath): ?ContentsCacheInterface
    {
        return $this->contentsCacheFactory !== null ?
            ($this->contentsCacheFactory)($boostCachePath) :
            null;
    }

    /**
     * @inheritDoc
     */
    public function getPackageFacadeFqcn(): string
    {
        return Charge::class;
    }

    /**
     * @inheritDoc
     */
    public function getPathFilter(): FileFilterInterface
    {
        return $this->pathFilter;
    }

    /**
     * @inheritDoc
     */
    public function getRealpathCacheKey(): string
    {
        return $this->realpathCacheKey;
    }

    /**
     * @inheritDoc
     */
    public function getRealpathCachePool(string $boostCachePath): ?CacheItemPoolInterface
    {
        return $this->realpathCachePoolFactory !== null ?
            ($this->realpathCachePoolFactory)($boostCachePath) :
            null;
    }

    /**
     * @inheritDoc
     */
    public function getStatCacheKey(): string
    {
        return $this->statCacheKey;
    }

    /**
     * @inheritDoc
     */
    public function getStatCachePool(string $boostCachePath): ?CacheItemPoolInterface
    {
        return $this->statCachePoolFactory !== null ?
            ($this->statCachePoolFactory)($boostCachePath) :
            null;
    }

    /**
     * @inheritDoc
     */
    public function shouldCacheNonExistentFiles(): bool
    {
        return $this->cacheNonExistentFiles;
    }

    /**
     * @inheritDoc
     */
    public function shouldHookBuiltinFunctions(): bool
    {
        return $this->hookBuiltinFunctions;
    }
}
