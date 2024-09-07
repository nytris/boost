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

use Psr\Cache\CacheItemPoolInterface;

/**
 * Class SinglePoolContentsCache.
 *
 * A file contents cache backed by a single PSR-6 cache pool.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class SinglePoolContentsCache implements ContentsCacheInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $psrCachePool,
        /**
         * The prefix to use for the keys of the cache within the cache pool.
         */
        private readonly string $cacheKeyPrefix = self::DEFAULT_CACHE_KEY_PREFIX
    ) {
    }

    /**
     * @inheritDoc
     */
    public function buildCacheKey(string $prefix, string $key, bool $isInclude): string
    {
        return $prefix . ($isInclude ? '_include_' : '_plain_') . hash('sha256', $key);
    }

    /**
     * @inheritDoc
     */
    public function getItemForPath(string $path, bool $isInclude): CachedFileInterface
    {
        $cacheKey = $this->buildCacheKey($this->cacheKeyPrefix, $path, $isInclude);

        return new CachedFile($this->psrCachePool, $this->psrCachePool->getItem($cacheKey));
    }

    /**
     * @inheritDoc
     */
    public function invalidatePath(string $path): void
    {
        // Invalidate any cache entries for both plain or include (shifted) variants.
        $this->psrCachePool->deleteItem($this->buildCacheKey($this->cacheKeyPrefix, $path, true));
        $this->psrCachePool->deleteItem($this->buildCacheKey($this->cacheKeyPrefix, $path, false));
    }
}
