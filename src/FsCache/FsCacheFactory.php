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
use Asmblah\PhpCodeShift\Shifter\Stream\Handler\Registration\RegistrantInterface;
use Nytris\Boost\Environment\EnvironmentInterface;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\Stream\Handler\FsCachingStreamHandlerRegistrant;
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
        private readonly EnvironmentInterface $environment,
        private readonly CanonicaliserInterface $canonicaliser
    ) {
    }

    /**
     * @inheritDoc
     */
    public function createStreamHandlerRegistrant(
        ?CacheItemPoolInterface $realpathPreloadCachePool,
        CacheItemPoolInterface $realpathCachePool,
        ?CacheItemPoolInterface $statPreloadCachePool,
        CacheItemPoolInterface $statCachePool,
        ?ContentsCacheInterface $contentsCache,
        string $realpathCacheKey,
        string $statCacheKey,
        bool $cacheNonExistentFiles,
        FileFilterInterface $pathFilter,
        bool $asVirtualFilesystem
    ): RegistrantInterface {
        // TODO: Remove now-unused $realpathCacheKey & $statCacheKey on next breaking 0.x bump.

        return new FsCachingStreamHandlerRegistrant(
            environment: $this->environment,
            canonicaliser: $this->canonicaliser,
            realpathPreloadCachePool: $realpathPreloadCachePool,
            realpathCachePool: $realpathCachePool,
            statPreloadCachePool: $statPreloadCachePool,
            statCachePool: $statCachePool,
            contentsCache: $contentsCache,
            cacheNonExistentFiles: $cacheNonExistentFiles,
            pathFilter: $pathFilter,
            asVirtualFilesystem: $asVirtualFilesystem
        );
    }
}
