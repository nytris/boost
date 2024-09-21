<?php

/*
 * Nytris Boost.
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/nytris/boost/
 *
 * Released under the MIT license.
 * https://github.com/nytris/boost/raw/main/MIT-LICENSE.txt
 */

declare(strict_types=1);

namespace Nytris\Boost\Tests\Functional;

use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapper;
use Mockery\MockInterface;
use Nytris\Boost\Boost;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class StandaloneTest.
 *
 * Tests Nytris Boost when used standalone, just via an instance of Boost,
 * rather than as a Nytris platform package.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class StandaloneTest extends AbstractFunctionalTestCase
{
    private ?Boost $boost = null;
    private MockInterface&CacheItemInterface $realpathCacheItem;
    private MockInterface&CacheItemPoolInterface $realpathCachePool;
    private MockInterface&CacheItemInterface $statCacheItemForIncludes;
    private MockInterface&CacheItemInterface $statCacheItemForNonIncludes;
    private MockInterface&CacheItemPoolInterface $statCachePool;

    public function setUp(): void
    {
        $this->realpathCachePool = mock(CacheItemPoolInterface::class, [
            'saveDeferred' => null,
        ]);
        $this->statCachePool = mock(CacheItemPoolInterface::class, [
            'saveDeferred' => null,
        ]);
        $this->realpathCacheItem = mock(CacheItemInterface::class, [
            'get' => [],
            'isHit' => true,
            'set' => null,
        ]);
        $this->statCacheItemForIncludes = mock(CacheItemInterface::class, [
            'get' => [],
            'isHit' => true,
            'set' => null,
        ]);
        $this->statCacheItemForNonIncludes = mock(CacheItemInterface::class, [
            'get' => [],
            'isHit' => true,
            'set' => null,
        ]);

        $this->realpathCachePool->allows()
            ->getItem('__my_realpath_cache')
            ->andReturn($this->realpathCacheItem)
            ->byDefault();
        $this->statCachePool->allows()
            ->getItem('__my_stat_cache_includes')
            ->andReturn($this->statCacheItemForIncludes)
            ->byDefault();
        $this->statCachePool->allows()
            ->getItem('__my_stat_cache_plain')
            ->andReturn($this->statCacheItemForNonIncludes)
            ->byDefault();
    }

    public function tearDown(): void
    {
        $this->boost?->uninstall();
    }

    public function testStreamWrapperIsNotInstalledWhenBoostInstantiatedButNotInstalled(): void
    {
        $this->boost = new Boost(
            realpathCachePool: $this->realpathCachePool,
            statCachePool: $this->statCachePool,
            realpathCacheKey: '__my_realpath_cache',
            statCacheKey: '__my_stat_cache'
        );

        $stream = fopen(__FILE__, 'rb');
        $metaData = stream_get_meta_data($stream);

        static::assertSame('plainfile', $metaData['wrapper_type']);
    }

    public function testStreamWrapperIsInstalledWhenBoostIsInstalled(): void
    {
        $path = __FILE__;
        $this->boost = new Boost(
            realpathCachePool: $this->realpathCachePool,
            statCachePool: $this->statCachePool,
            realpathCacheKey: '__my_realpath_cache',
            statCacheKey: '__my_stat_cache'
        );

        $this->boost->install();
        $stream = fopen($path, 'rb');
        $metaData = stream_get_meta_data($stream);

        static::assertSame('user-space', $metaData['wrapper_type']);
        /** @var StreamWrapper $streamWrapper */
        $streamWrapper = $metaData['wrapper_data'];
        static::assertInstanceOf(StreamWrapper::class, $streamWrapper);
        static::assertSame($path, $streamWrapper->getOpenPath());
    }
}
