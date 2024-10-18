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

namespace Nytris\Boost\FsCache\Directory;

use Asmblah\PhpCodeShift\Shifter\Filter\FileFilterInterface;
use Nytris\Boost\FsCache\Directory\Directory\CachedDirectory;
use Nytris\Boost\FsCache\Directory\Directory\DirectoryInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class PoolBackedPartition.
 *
 * Stores directory entries for specific directories defined by a filter.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class PoolBackedPartition implements PartitionInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $psrCachePool,
        /**
         * Specifies which paths this partition is responsible for.
         */
        private readonly FileFilterInterface $pathFilter,
        /**
         * The prefix to use for the keys of the cache within the cache pool.
         */
        private readonly string $cacheKeyPrefix = DirectoryCacheInterface::DEFAULT_CACHE_KEY_PREFIX
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getItemForPath(
        string $path,
        DirectoryCacheInterface $directoryCache
    ): ?DirectoryInterface {
        if (!$this->pathFilter->fileMatches($path)) {
            // This partition is not responsible for this path.
            return null;
        }

        $cacheKey = $directoryCache->buildCacheKey($this->cacheKeyPrefix, $path);

        return new CachedDirectory($this->psrCachePool, $this->psrCachePool->getItem($cacheKey));
    }

    /**
     * @inheritDoc
     */
    public function invalidatePath(string $path, DirectoryCacheInterface $directoryCache): bool
    {
        if (!$this->pathFilter->fileMatches($path)) {
            // This partition is not responsible for this path.
            return false;
        }

        // Invalidate any cache entries.
        $this->psrCachePool->deleteItem($directoryCache->buildCacheKey($this->cacheKeyPrefix, $path));

        return true;
    }
}
