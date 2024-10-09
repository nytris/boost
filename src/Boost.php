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

use Asmblah\PhpCodeShift\Shifter\Filter\FileFilter;
use Asmblah\PhpCodeShift\Shifter\Filter\FileFilterInterface;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\FsCache;
use Nytris\Boost\FsCache\FsCacheFactory;
use Nytris\Boost\FsCache\FsCacheInterface;
use Nytris\Boost\Library\Library;
use Nytris\Boost\Library\LibraryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Class Boost.
 *
 * Defines the public facade API for the library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class Boost implements BoostInterface
{
    private readonly FsCacheInterface $fsCache;
    private readonly ?FileFilterInterface $hookBuiltinFunctionsFilter;

    public function __construct(
        /**
         * When standalone, a library will be created here if not provided.
         * When installed as a Nytris package, Charge provides a single shared Library instance.
         */
        private readonly LibraryInterface $library = new Library(),
        ?FsCacheInterface $fsCache = null,
        /**
         * Cache pool in which to persist the realpath cache.
         *
         * Set to null to disable PSR cache persistence.
         * Cache will still be maintained for the life of the request/CLI process.
         */
        ?CacheItemPoolInterface $realpathCachePool = null,
        /**
         * Cache pool in which to persist the stat cache.
         *
         * Set to null to disable PSR cache persistence.
         * Cache will still be maintained for the life of the request/CLI process.
         */
        ?CacheItemPoolInterface $statCachePool = null,
        /**
         * @deprecated Unused - use the cache pool namespace.
         */
        string $realpathCacheKey = FsCacheInterface::DEFAULT_REALPATH_CACHE_KEY,
        /**
         * @deprecated Unused - use the cache pool namespace.
         */
        string $statCacheKey = FsCacheInterface::DEFAULT_STAT_CACHE_KEY,
        /**
         * Whether to hook built-in functions such as clearstatcache(...).
         */
        FileFilterInterface|bool $hookBuiltinFunctions = true,
        /**
         * Whether the non-existence of files should be cached in the realpath cache.
         */
        bool $cacheNonExistentFiles = true,
        /**
         * Cache in which to store file contents.
         *
         * Set to null to disable contents caching.
         */
        ?ContentsCacheInterface $contentsCache = null,
        /**
         * Filter for which file paths to cache in the realpath, stat and contents caches.
         */
        FileFilterInterface $pathFilter = new FileFilter('**'),
        /**
         * In virtual-filesystem mode, the cache is write-allocate with no write-through
         * to the next stream handler in the chain (usually the original one, which persists to disk).
         */
        bool $asVirtualFilesystem = false,
        /**
         * Read-only cache pool from which to preload the realpath cache.
         *
         * Set to null to disable preloading from a PSR cache.
         */
        ?CacheItemPoolInterface $realpathPreloadCachePool = null,
        /**
         * Read-only cache pool from which to preload the stat cache.
         *
         * Set to null to disable preloading from a PSR cache.
         */
        ?CacheItemPoolInterface $statPreloadCachePool = null
    ) {
        $this->hookBuiltinFunctionsFilter = match ($hookBuiltinFunctions) {
            true => new FileFilter('**'),
            false => null,
            default => $hookBuiltinFunctions,
        };

        $environment = $library->getEnvironment();

        // Just cache in memory if persistence is disabled.
        $realpathCachePool ??= new ArrayAdapter();
        $statCachePool ??= new ArrayAdapter();

        $this->fsCache = $fsCache ?? new FsCache(
            new FsCacheFactory($environment, $library->getCanonicaliser()),
            $realpathPreloadCachePool,
            $realpathCachePool,
            $statPreloadCachePool,
            $statCachePool,
            $contentsCache,
            $realpathCacheKey,
            $statCacheKey,
            $cacheNonExistentFiles,
            $pathFilter,
            $asVirtualFilesystem
        );
    }

    /**
     * @inheritDoc
     */
    public function getInMemoryRealpathEntryCache(): array
    {
        return $this->fsCache->getInMemoryRealpathEntryCache();
    }

    /**
     * @inheritDoc
     */
    public function getInMemoryStatEntryCache(): array
    {
        return $this->fsCache->getInMemoryStatEntryCache();
    }

    /**
     * @inheritDoc
     */
    public function getLibrary(): LibraryInterface
    {
        return $this->library;
    }

    /**
     * @inheritDoc
     */
    public function install(): void
    {
        if ($this->hookBuiltinFunctionsFilter !== null) {
            $this->library->hookBuiltinFunctions($this->hookBuiltinFunctionsFilter);
        }

        $this->fsCache->install();

        $this->library->addBoost($this);
    }

    /**
     * @inheritDoc
     */
    public function invalidateCaches(): void
    {
        $this->fsCache->invalidateCaches();
    }

    /**
     * @inheritDoc
     */
    public function uninstall(): void
    {
        $this->library->removeBoost($this);

        $this->fsCache->uninstall();
    }
}
