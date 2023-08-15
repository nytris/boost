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

use Asmblah\PhpCodeShift\CodeShiftInterface;
use Asmblah\PhpCodeShift\Shifter\Shift\Shift\FunctionHook\FunctionHookShiftSpec;
use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\StreamWrapperManager;
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
        private readonly CodeShiftInterface $codeShift,
        private readonly FsCacheFactoryInterface $fsCacheFactory,
        /**
         * Set to null to disable PSR cache persistence.
         * Caches will still be maintained for the life of the request/CLI process.
         */
        private readonly ?CacheItemPoolInterface $realpathCachePool = null,
        private readonly ?CacheItemPoolInterface $statCachePool = null,
        private readonly string $realpathCacheKey = self::DEFAULT_REALPATH_CACHE_KEY,
        private readonly string $statCacheKey = self::DEFAULT_STAT_CACHE_KEY,
        /**
         * Whether to hook built-in functions such as clearstatcache(...).
         */
        private readonly bool $hookBuiltinFunctions = true
    ) {
        /**
         * To reduce I/O, defer PSR cache persistence (if enabled)
         * until the end of the request or CLI process.
         */
        register_shutdown_function(function () {
            $this->persistCaches();
        });
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
            $this->realpathCacheKey,
            $this->statCacheKey
        );

        if ($this->hookBuiltinFunctions) {
            // Hook the clearstatcache() function and simply have it fully clear both caches for now.
            // TODO: Implement parameters.
            $this->codeShift->shift(
                new FunctionHookShiftSpec(
                    'clearstatcache',
                    fn($original) => function () use ($original): void {
                        $this->fsCachingStreamHandler->invalidateCaches();
                    }
                )
            );
        }

        StreamWrapperManager::setStreamHandler($this->fsCachingStreamHandler);
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
