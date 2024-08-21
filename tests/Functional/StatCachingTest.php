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
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class StatCachingTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class StatCachingTest extends AbstractFunctionalTestCase
{
    private Boost $boost;
    private MockInterface&CacheItemInterface $realpathCacheItem;
    private MockInterface&CacheItemPoolInterface $realpathCachePool;
    private MockInterface&CacheItemInterface $statCacheItem;
    private MockInterface&CacheItemPoolInterface $statCachePool;
    private string $varPath;

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
        $this->statCacheItem = mock(CacheItemInterface::class, [
            'get' => [
                'includes' => [],
                'plain' => [],
            ],
            'isHit' => true,
            'set' => null,
        ]);

        $this->varPath = __DIR__ . '/../../var';
        @mkdir($this->varPath, recursive: true);

        $this->boost = new Boost(
            realpathCachePool: $this->realpathCachePool,
            statCachePool: $this->statCachePool,
            realpathCacheKey: '__my_realpath_cache',
            statCacheKey: '__my_stat_cache'
        );

        $this->realpathCachePool->allows()
            ->getItem('__my_realpath_cache')
            ->andReturn($this->realpathCacheItem);
        $this->statCachePool->allows()
            ->getItem('__my_stat_cache')
            ->andReturn($this->statCacheItem);
    }

    public function tearDown(): void
    {
        $this->boost->uninstall();

        $this->rimrafDescendantsOf($this->varPath);
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
                'includes' => [],
                'plain' => [
                    $imaginaryPath => $actualPathStat,
                ],
            ]);
        $this->boost->install();

        static::assertEquals(stat($imaginaryPath), $actualPathStat);
        static::assertTrue(file_exists($imaginaryPath));
        static::assertTrue(is_file($imaginaryPath));
        static::assertFalse(is_dir($imaginaryPath));
    }

    public function testStatCacheIsPersistedOnDestructionWhenChangesMade(): void
    {
        $this->statCachePool->expects()
            ->saveDeferred($this->statCacheItem)
            ->once();

        $this->boost->install();
        file_put_contents($this->varPath . '/my_file.txt', 'my contents');
        $this->boost->uninstall();
    }

    public function testStatCacheIsNotPersistedOnDestructionWhenNoChangesMade(): void
    {
        $this->statCachePool->expects()
            ->saveDeferred($this->statCacheItem)
            ->never();

        $this->boost->install();
        $this->boost->uninstall();
    }
}
