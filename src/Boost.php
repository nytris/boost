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

use Asmblah\PhpCodeShift\CodeShift;
use Asmblah\PhpCodeShift\CodeShiftInterface;
use Asmblah\PhpCodeShift\Shifter\Filter\FileFilter;
use Asmblah\PhpCodeShift\Shifter\Filter\FileFilterInterface;
use Nytris\Boost\Environment\Environment;
use Nytris\Boost\FsCache\Canonicaliser;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\FsCache;
use Nytris\Boost\FsCache\FsCacheFactory;
use Nytris\Boost\FsCache\FsCacheInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class Boost.
 *
 * Defines the public facade API for the library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class Boost implements BoostInterface
{
    private readonly CodeShiftInterface $codeShift;
    private readonly FsCacheInterface $fsCache;

    public function __construct(
        ?CodeShiftInterface $codeShift = null,
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
        string $realpathCacheKey = FsCacheInterface::DEFAULT_REALPATH_CACHE_KEY,
        string $statCacheKey = FsCacheInterface::DEFAULT_STAT_CACHE_KEY,
        /**
         * Whether to hook built-in functions such as clearstatcache(...).
         */
        bool $hookBuiltinFunctions = true,
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
        FileFilterInterface $pathFilter = new FileFilter('**')
    ) {
        $this->codeShift = $codeShift ?? new CodeShift();
        $this->fsCache = $fsCache ?? new FsCache(
            $this->codeShift,
            new FsCacheFactory(new Canonicaliser(new Environment())),
            $realpathCachePool,
            $statCachePool,
            $contentsCache,
            $realpathCacheKey,
            $statCacheKey,
            $hookBuiltinFunctions,
            $cacheNonExistentFiles,
            $pathFilter
        );
    }

    /**
     * @inheritDoc
     */
    public function install(): void
    {
        $this->fsCache->install();

        $this->codeShift->install();
    }

    /**
     * @inheritDoc
     */
    public function uninstall(): void
    {
        $this->fsCache->uninstall();

        $this->codeShift->uninstall();
    }
}
