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

namespace Nytris\Boost\FsCache\Stream\Handler;

use Asmblah\PhpCodeShift\Shifter\Filter\FileFilterInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Handler\Registration\RegistrantInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Handler\Registration\Registration;
use Asmblah\PhpCodeShift\Shifter\Stream\Handler\Registration\RegistrationInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Nytris\Boost\Environment\EnvironmentInterface;
use Nytris\Boost\FsCache\CanonicaliserInterface;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\Realpath\RealpathCache;
use Nytris\Boost\FsCache\Stat\StatCache;
use Nytris\Boost\FsCache\Stream\Opener\StreamOpener;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class FsCachingStreamHandlerRegistrant.
 *
 * @phpstan-implements RegistrantInterface<FsCachingStreamHandlerInterface>
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class FsCachingStreamHandlerRegistrant implements RegistrantInterface
{
    public function __construct(
        private readonly EnvironmentInterface $environment,
        private readonly CanonicaliserInterface $canonicaliser,
        private readonly ?CacheItemPoolInterface $realpathPreloadCachePool,
        private readonly CacheItemPoolInterface $realpathCachePool,
        private readonly ?CacheItemPoolInterface $statPreloadCachePool,
        private readonly CacheItemPoolInterface $statCachePool,
        private readonly ?ContentsCacheInterface $contentsCache,
        private readonly bool $cacheNonExistentFiles,
        private readonly FileFilterInterface $pathFilter,
        private readonly bool $asVirtualFilesystem
    ) {
    }

    /**
     * @inheritDoc
     */
    public function registerStreamHandler(
        StreamHandlerInterface $currentStreamHandler,
        ?StreamHandlerInterface $previousStreamHandler
    ): RegistrationInterface {
        $realpathCache = new RealpathCache(
            $currentStreamHandler,
            $this->canonicaliser,
            $this->realpathPreloadCachePool,
            $this->realpathCachePool,
            $this->cacheNonExistentFiles,
            $this->asVirtualFilesystem
        );
        $statCache = new StatCache(
            $currentStreamHandler,
            $this->environment,
            $this->canonicaliser,
            $realpathCache,
            $this->statPreloadCachePool,
            $this->statCachePool,
            $this->asVirtualFilesystem
        );
        $streamOpener = new StreamOpener(
            $currentStreamHandler,
            $realpathCache,
            $statCache,
            $this->contentsCache,
            $this->asVirtualFilesystem
        );

        return new Registration(
            new FsCachingStreamHandler(
                $currentStreamHandler,
                $this->environment,
                $streamOpener,
                $realpathCache,
                $statCache,
                $this->contentsCache,
                $this->pathFilter,
                $this->asVirtualFilesystem
            ),
            $currentStreamHandler
        );
    }
}
