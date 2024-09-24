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
use Nytris\Boost\Boost;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

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
    private CacheItemPoolInterface $realpathCachePool;
    private CacheItemPoolInterface $statCachePool;

    public function setUp(): void
    {
        $this->realpathCachePool = new ArrayAdapter();
        $this->statCachePool = new ArrayAdapter();
    }

    public function tearDown(): void
    {
        $this->boost?->uninstall();
    }

    public function testStreamWrapperIsNotInstalledWhenBoostInstantiatedButNotInstalled(): void
    {
        $this->boost = new Boost(
            realpathCachePool: $this->realpathCachePool,
            statCachePool: $this->statCachePool
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
            statCachePool: $this->statCachePool
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
