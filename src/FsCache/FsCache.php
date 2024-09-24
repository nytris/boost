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

use Asmblah\PhpCodeShift\Shifter\Filter\ExceptFilter;
use Asmblah\PhpCodeShift\Shifter\Filter\FileFilter;
use Asmblah\PhpCodeShift\Shifter\Filter\FileFilterInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\StreamWrapperManager;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\Stream\Handler\FsCachingStreamHandlerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class FsCache.
 *
 * Emulates the PHP realpath and stat caches in userland, even when open_basedir is enabled.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class FsCache implements FsCacheInterface
{
    private ?FsCachingStreamHandlerInterface $fsCachingStreamHandler = null;
    private ?StreamHandlerInterface $originalStreamHandler;

    public function __construct(
        private readonly FsCacheFactoryInterface $fsCacheFactory,
        private readonly CacheItemPoolInterface $realpathCachePool,
        private readonly CacheItemPoolInterface $statCachePool,
        private readonly ?ContentsCacheInterface $contentsCache,
        private readonly string $realpathCacheKey,
        private readonly string $statCacheKey,
        /**
         * Whether the non-existence of files should be cached in the realpath cache.
         */
        private readonly bool $cacheNonExistentFiles,
        private readonly FileFilterInterface $pathFilter,
        private readonly bool $asVirtualFilesystem
    ) {
    }

    /**
     * @inheritDoc
     */
    public function install(): void
    {
        $this->originalStreamHandler = StreamWrapperManager::getStreamHandler();

        $this->fsCachingStreamHandler = $this->fsCacheFactory->createStreamHandler(
            $this->originalStreamHandler,
            $this->realpathCachePool,
            $this->statCachePool,
            $this->contentsCache,
            $this->realpathCacheKey,
            $this->statCacheKey,
            $this->cacheNonExistentFiles,
            // Exclude Boost's own source from being cached to prevent a catch-22.
            new ExceptFilter(
                new FileFilter(dirname(__DIR__) . '/**'),
                $this->pathFilter
            ),
            $this->asVirtualFilesystem
        );

        StreamWrapperManager::setStreamHandler($this->fsCachingStreamHandler);
    }

    /**
     * @inheritDoc
     */
    public function invalidateCaches(): void
    {
        $this->fsCachingStreamHandler->invalidateCaches();
    }

    /**
     * Persists the filesystem caches to PSR cache.
     */
    private function persistCaches(): void
    {
        if ($this->fsCachingStreamHandler === null) {
            return; // Not installed.
        }

        $this->fsCachingStreamHandler->persistRealpathCache();
        $this->fsCachingStreamHandler->persistStatCache();
    }

    /**
     * @inheritDoc
     */
    public function uninstall(): void
    {
        if ($this->fsCachingStreamHandler === null) {
            return; // Not installed.
        }

        $this->persistCaches();
        $this->fsCachingStreamHandler = null;

        StreamWrapperManager::setStreamHandler($this->originalStreamHandler);
        $this->originalStreamHandler = null;
    }
}
