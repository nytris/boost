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
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class MultiplePoolDirectoryCache.
 *
 * A directory entries cache backed by multiple PSR-6 cache pools.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class MultiplePoolDirectoryCache implements DirectoryCacheInterface
{
    /**
     * @param PartitionInterface[] $partitions Prioritised list of partitions responsible for specific directories.
     * @param CacheItemPoolInterface $fallbackPsrCachePool
     * @param string $fallbackCacheKeyPrefix
     */
    public function __construct(
        private readonly array $partitions,
        private readonly CacheItemPoolInterface $fallbackPsrCachePool,
        /**
         * The prefix to use for the keys of the cache within the fallback cache pool.
         */
        private readonly string $fallbackCacheKeyPrefix = self::DEFAULT_CACHE_KEY_PREFIX
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
        foreach ($this->partitions as $partition) {
            $item = $partition->getItemForPath($path, $this);

            if ($item !== null) {
                return $item; // This partition is responsible for this path, so we can early-out.
            }
        }

        // No partition claimed this path, so use the fallback pool.
        $cacheKey = $this->buildCacheKey($this->fallbackCacheKeyPrefix, $path);

        return new CachedDirectory($this->fallbackPsrCachePool, $this->fallbackPsrCachePool->getItem($cacheKey));
    }

    /**
     * @inheritDoc
     */
    public function invalidatePath(string $path): void
    {
        foreach ($this->partitions as $partition) {
            if ($partition->invalidatePath($path, $this) === true) {
                return; // This partition is responsible for this path, so we can early-out.
            }
        }

        // No partition claimed this path, invalidate any cache entries in the fallback pool.
        $this->fallbackPsrCachePool->deleteItem($this->buildCacheKey($this->fallbackCacheKeyPrefix, $path));
    }
}
