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

namespace Nytris\Boost\FsCache;

use Asmblah\PhpCodeShift\Shifter\Filter\FileFilterInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\Stream\Handler\FsCachingStreamHandler;
use Nytris\Boost\FsCache\Stream\Handler\FsCachingStreamHandlerInterface;
use Nytris\Boost\FsCache\Stream\Opener\StreamOpener;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class FsCacheFactory.
 *
 * Handles creation of filesystem-cache-related objects.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class FsCacheFactory implements FsCacheFactoryInterface
{
    public function __construct(
        private readonly CanonicaliserInterface $canonicaliser
    ) {
    }

    /**
     * @inheritDoc
     */
    public function createStreamHandler(
        StreamHandlerInterface $originalStreamHandler,
        ?CacheItemPoolInterface $realpathCachePool,
        ?CacheItemPoolInterface $statCachePool,
        ?ContentsCacheInterface $contentsCache,
        string $realpathCacheKey,
        string $statCacheKey,
        /**
         * Whether the non-existence of files should be cached in the realpath cache.
         */
        bool $cacheNonExistentFiles,
        FileFilterInterface $pathFilter
    ): FsCachingStreamHandlerInterface {
        return new FsCachingStreamHandler(
            $originalStreamHandler,
            new StreamOpener(
                $originalStreamHandler,
                $this->canonicaliser,
                $contentsCache
            ),
            $this->canonicaliser,
            $realpathCachePool,
            $statCachePool,
            $contentsCache,
            $realpathCacheKey,
            $statCacheKey,
            $cacheNonExistentFiles,
            $pathFilter
        );
    }
}
