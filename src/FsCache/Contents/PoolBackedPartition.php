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

namespace Nytris\Boost\FsCache\Contents;

use Asmblah\PhpCodeShift\Shifter\Filter\FileFilterInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class PoolBackedPartition.
 *
 * Stores contents for specific files defined by a filter.
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
        private readonly string $cacheKeyPrefix = ContentsCacheInterface::DEFAULT_CACHE_KEY_PREFIX
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getItemForPath(
        string $path,
        bool $isInclude,
        ContentsCacheInterface $contentsCache
    ): ?CachedFileInterface {
        if (!$this->pathFilter->fileMatches($path)) {
            // This partition is not responsible for this path.
            return null;
        }

        $cacheKey = $contentsCache->buildCacheKey($this->cacheKeyPrefix, $path, $isInclude);

        return new CachedFile($this->psrCachePool, $this->psrCachePool->getItem($cacheKey));
    }

    /**
     * @inheritDoc
     */
    public function invalidatePath(string $path, ContentsCacheInterface $contentsCache): bool
    {
        if (!$this->pathFilter->fileMatches($path)) {
            // This partition is not responsible for this path.
            return false;
        }

        // Invalidate any cache entries for both plain or include (shifted) variants.
        $this->psrCachePool->deleteItem($contentsCache->buildCacheKey($this->cacheKeyPrefix, $path, true));
        $this->psrCachePool->deleteItem($contentsCache->buildCacheKey($this->cacheKeyPrefix, $path, false));

        return true;
    }
}
