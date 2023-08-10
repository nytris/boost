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

use Mockery\MockInterface;
use Nytris\Boost\Boost;
use Nytris\Boost\Tests\AbstractTestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class StatCachingTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class StatCachingTest extends AbstractTestCase
{
    private ?Boost $boost;
    /**
     * @var (MockInterface&CacheItemPoolInterface)|null
     */
    private $cachePool;
    /**
     * @var (MockInterface&CacheItemInterface)|null
     */
    private $realpathCacheItem;
    /**
     * @var (MockInterface&CacheItemInterface)|null
     */
    private $statCacheItem;

    public function setUp(): void
    {
        $this->cachePool = mock(CacheItemPoolInterface::class, [
            'saveDeferred' => null,
        ]);
        $this->realpathCacheItem = mock(CacheItemInterface::class, [
            'get' => [],
            'isHit' => true,
            'set' => null,
        ]);
        $this->statCacheItem = mock(CacheItemInterface::class, [
            'get' => [],
            'isHit' => true,
            'set' => null,
        ]);

        $this->boost = new Boost(cachePool: $this->cachePool, cachePrefix: '__test_');

        $this->cachePool->allows()
            ->getItem('__test_realpath_cache')
            ->andReturn($this->realpathCacheItem);
        $this->cachePool->allows()
            ->getItem('__test_stat_cache')
            ->andReturn($this->statCacheItem);
    }

    public function tearDown(): void
    {
        $this->boost->uninstall();
    }

    public function testStatCacheCanRepointAPathToADifferentInode(): void
    {
        $actualPath = __DIR__ . '/Fixtures/my_actual_file.php';
        $imaginaryPath = __DIR__ . '/Fixtures/my_imaginary_file.php';
        $actualPathStat = stat($actualPath);
        $this->realpathCacheItem->allows()
            ->get()
            ->andReturn([
                $imaginaryPath => [
                    // Unlike the test above, the realpath cache has the imaginary path as the target.
                    'realpath' => $imaginaryPath,
                ]
            ]);
        $this->statCacheItem->allows()
            ->get()
            ->andReturn([
                $imaginaryPath => $actualPathStat,
            ]);
        $this->boost->install();

        static::assertEquals(stat($imaginaryPath), $actualPathStat);
        static::assertTrue(file_exists($imaginaryPath));
        static::assertTrue(is_file($imaginaryPath));
        static::assertFalse(is_dir($imaginaryPath));
    }
}
