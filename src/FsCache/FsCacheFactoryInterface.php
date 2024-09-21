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
use Nytris\Boost\FsCache\Stream\Handler\FsCachingStreamHandlerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Interface FsCacheFactoryInterface.
 *
 * Handles creation of filesystem-cache-related objects.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface FsCacheFactoryInterface
{
    /**
     * Creates a filesystem-caching stream handler.
     */
    public function createStreamHandler(
        StreamHandlerInterface $originalStreamHandler,
        ?CacheItemPoolInterface $realpathCachePool,
        ?CacheItemPoolInterface $statCachePool,
        ?ContentsCacheInterface $contentsCache,
        string $realpathCacheKey,
        string $statCacheKey,
        bool $cacheNonExistentFiles,
        FileFilterInterface $pathFilter,
        bool $asVirtualFilesystem
    ): FsCachingStreamHandlerInterface;
}
