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

namespace Nytris\Boost\Tests\Unit\FsCache\Stream\Handler;

use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapperInterface;
use Mockery\MockInterface;
use Nytris\Boost\FsCache\CanonicaliserInterface;
use Nytris\Boost\FsCache\Stream\Handler\FsCachingStreamHandler;
use Nytris\Boost\Tests\AbstractTestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class FsCachingStreamHandlerTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class FsCachingStreamHandlerTest extends AbstractTestCase
{
    private MockInterface&CanonicaliserInterface $canonicaliser;
    private FsCachingStreamHandler $fsCachingStreamHandler;
    private MockInterface&CacheItemPoolInterface $realpathCachePool;
    private MockInterface&CacheItemInterface $realpathCachePoolItem;
    private MockInterface&CacheItemPoolInterface $statCachePool;
    private MockInterface&CacheItemInterface $statCachePoolItem;
    private MockInterface&StreamWrapperInterface $streamWrapper;
    private MockInterface&StreamHandlerInterface $wrappedStreamHandler;

    public function setUp(): void
    {
        $this->canonicaliser = mock(CanonicaliserInterface::class);
        $this->realpathCachePool = mock(CacheItemPoolInterface::class);
        $this->realpathCachePoolItem = mock(CacheItemInterface::class, [
            'get' => [
                '/my/resolved/../path/to/my_module.php' => [
                    'canonical' => '/my/path/to/my_module.php',
                ],
            ],
            'isHit' => true,
        ]);
        $this->statCachePool = mock(CacheItemPoolInterface::class);
        $this->statCachePoolItem = mock(CacheItemInterface::class, [
            'get' => [
                'includes' => [
                    '/my/path/to/my_module.php' => [
                        'my_fake_include_stat' => 'yes',
                    ],
                ],
                'plain' => [
                    '/my/file.txt' => [
                        'my_fake_plain_stat' => 'yes',
                    ],
                ],
            ],
            'isHit' => true,
        ]);
        $this->streamWrapper = mock(StreamWrapperInterface::class);
        $this->wrappedStreamHandler = mock(StreamHandlerInterface::class);

        $this->canonicaliser->allows('canonicalise')
            ->andReturnArg(0)
            ->byDefault();

        $this->realpathCachePool->allows()
            ->getItem('my_realpath_cache_key')
            ->andReturn($this->realpathCachePoolItem)
            ->byDefault();

        $this->statCachePool->allows()
            ->getItem('my_stat_cache_key')
            ->andReturn($this->statCachePoolItem)
            ->byDefault();

        $this->wrappedStreamHandler->allows('unwrapped')
            ->andReturnUsing(fn (callable $callback) => $callback())
            ->byDefault();

        $this->fsCachingStreamHandler = new FsCachingStreamHandler(
            $this->wrappedStreamHandler,
            $this->canonicaliser,
            $this->realpathCachePool,
            $this->statCachePool,
            realpathCacheKey: 'my_realpath_cache_key',
            statCacheKey: 'my_stat_cache_key'
        );
    }

    public function testCacheRealpathCanCacheARealpath(): void
    {
        $this->fsCachingStreamHandler->cacheRealpath(
            canonicalPath: '/my/custom/canonical/path',
            realpath: '/my/custom/real/path'
        );

        static::assertSame(
            '/my/custom/real/path',
            $this->fsCachingStreamHandler->getRealpath('/my/custom/canonical/path')
        );
        static::assertSame(
            '/my/custom/real/path',
            $this->fsCachingStreamHandler->getRealpath('/my/custom/real/path')
        );
        static::assertEquals(
            [
                'realpath' => '/my/custom/real/path',
            ],
            $this->fsCachingStreamHandler->getRealpathCacheEntry('/my/custom/canonical/path')
        );
    }

    public function testStreamStatRetrievesACachedIncludeStat(): void
    {
        $this->streamWrapper->allows()
            ->getOpenPath()
            ->andReturn('/my/path/to/my_module.php');
        $this->streamWrapper->allows()
            ->isInclude()
            ->andReturnTrue();
        $this->fsCachingStreamHandler->cacheRealpath(
            canonicalPath: '/my/path/to/my_module.php',
            realpath: '/my/path/to/my_module.php'
        );

        static::assertEquals(
            ['my_fake_include_stat' => 'yes'],
            $this->fsCachingStreamHandler->streamStat($this->streamWrapper)
        );
    }

    public function testStreamStatDoesNotRetrieveACachedIncludeStatForPlainStream(): void
    {
        $this->streamWrapper->allows()
            ->getOpenPath()
            ->andReturn('/my/path/to/my_module.php');
        $this->streamWrapper->allows()
            ->isInclude()
            ->andReturnFalse();
        $this->fsCachingStreamHandler->cacheRealpath(
            canonicalPath: '/my/path/to/my_module.php',
            realpath: '/my/path/to/my_module.php'
        );
        $this->wrappedStreamHandler->allows()
            ->streamStat($this->streamWrapper)
            ->andReturnFalse();

        static::assertFalse($this->fsCachingStreamHandler->streamStat($this->streamWrapper));
    }

    public function testStreamStatRetrievesACachedPlainStat(): void
    {
        $this->streamWrapper->allows()
            ->getOpenPath()
            ->andReturn('/my/file.txt');
        $this->streamWrapper->allows()
            ->isInclude()
            ->andReturnFalse();
        $this->fsCachingStreamHandler->cacheRealpath(
            canonicalPath: '/my/file.txt',
            realpath: '/my/file.txt'
        );

        static::assertEquals(
            ['my_fake_plain_stat' => 'yes'],
            $this->fsCachingStreamHandler->streamStat($this->streamWrapper)
        );
    }

    public function testStreamStatDoesNotRetrieveACachedPlainStatForIncludeStream(): void
    {
        $this->streamWrapper->allows()
            ->getOpenPath()
            ->andReturn('/my/file.txt');
        $this->streamWrapper->allows()
            ->isInclude()
            ->andReturnTrue();
        $this->fsCachingStreamHandler->cacheRealpath(
            canonicalPath: '/my/file.txt',
            realpath: '/my/file.txt'
        );
        $this->wrappedStreamHandler->allows()
            ->streamStat($this->streamWrapper)
            ->andReturnFalse();

        static::assertFalse($this->fsCachingStreamHandler->streamStat($this->streamWrapper));
    }

    public function testUrlStatRetrievesACachedPlainStat(): void
    {
        $this->fsCachingStreamHandler->cacheRealpath(
            canonicalPath: '/my/file.txt',
            realpath: '/my/file.txt'
        );

        static::assertEquals(
            ['my_fake_plain_stat' => 'yes'],
            $this->fsCachingStreamHandler->urlStat('/my/file.txt', 0)
        );
    }
}