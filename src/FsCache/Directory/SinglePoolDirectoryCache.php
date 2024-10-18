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

use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapperInterface;
use Nytris\Boost\FsCache\Directory\Directory\CachedDirectory;
use Nytris\Boost\FsCache\Directory\Directory\DirectoryInterface;
use Nytris\Boost\FsCache\Directory\Directory\FreshDirectory;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class SinglePoolDirectoryCache.
 *
 * A directory entries cache backed by a single PSR-6 cache pool.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class SinglePoolDirectoryCache implements DirectoryCacheInterface
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
    public function buildCacheKey(string $prefix, string $key): string
    {
        return $prefix . hash('sha256', $key);
    }

    /**
     * @inheritDoc
     */
    public function getItemForPath(
        string $path,
        StreamWrapperInterface $streamWrapper,
        StreamHandlerInterface $wrappedStreamHandler
    ): DirectoryInterface {
        $cacheKey = $this->buildCacheKey($this->cacheKeyPrefix, $path);
        $cacheItem = $this->psrCachePool->getItem($cacheKey);

        return $cacheItem->isHit() ?
            new CachedDirectory($cacheItem) :
            new FreshDirectory($wrappedStreamHandler, $streamWrapper, $this->psrCachePool, $cacheItem);
    }

    /**
     * @inheritDoc
     */
    public function invalidatePath(string $path): void
    {
        // Invalidate any cache entries.
        $this->psrCachePool->deleteItem($this->buildCacheKey($this->cacheKeyPrefix, $path));
    }
}
