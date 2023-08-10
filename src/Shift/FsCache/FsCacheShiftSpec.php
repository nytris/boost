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

namespace Nytris\Boost\Shift\FsCache;

use Asmblah\PhpCodeShift\CodeShiftFacadeInterface;
use Asmblah\PhpCodeShift\Shifter\Shift\Shift\FunctionHook\FunctionHookShiftSpec;
use Asmblah\PhpCodeShift\Shifter\Shift\Spec\ShiftSpecInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\StreamWrapperManager;
use Nytris\Boost\Shift\FsCache\Stream\Handler\FsCachingStreamHandler;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class FsCacheShiftSpec.
 *
 * Emulates the PHP realpath cache in userland, even when open_basedir is enabled.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class FsCacheShiftSpec implements ShiftSpecInterface
{
    public function __construct(
        private readonly CodeShiftFacadeInterface $codeShift,
        /**
         * Set to null to disable PSR cache persistence.
         * Caches will still be maintained for the life of the request/CLI process.
         */
        private readonly ?CacheItemPoolInterface $cachePool = null,
        private readonly string $cachePrefix = '__nytris_boost_'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        $originalStreamHandler = StreamWrapperManager::getStreamHandler();

        $fsCachingStreamHandler = new FsCachingStreamHandler(
            $originalStreamHandler,
            $this->cachePool,
            $this->cachePrefix
        );

        // Hook the clearstatcache() function and simply have it fully clear both caches for now.
        // TODO: Implement parameters.
        $this->codeShift->shift(
            new FunctionHookShiftSpec(
                'clearstatcache',
                fn ($original) => function () use ($fsCachingStreamHandler, $original): void {
                    $fsCachingStreamHandler->invalidateCaches();
                }
            )
        );

        StreamWrapperManager::setStreamHandler($fsCachingStreamHandler);
    }
}
