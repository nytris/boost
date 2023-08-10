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

use Asmblah\PhpCodeShift\CodeShift;
use Mockery\MockInterface;
use Nytris\Boost\Shift\FsCache\FsCacheShiftSpec;
use Nytris\Boost\Shift\FsCache\FsCacheShiftType;
use Nytris\Boost\Tests\AbstractTestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class RealpathCachingTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class RealpathCachingTest extends AbstractTestCase
{
    /**
     * @var (MockInterface&CacheItemPoolInterface)|null
     */
    private $cachePool;
    private ?CodeShift $codeShift;
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

        $this->codeShift = new CodeShift();
        $this->codeShift->registerShiftType(new FsCacheShiftType());

        $this->cachePool->allows()
            ->getItem('__test_realpath_cache')
            ->andReturn($this->realpathCacheItem);
        $this->cachePool->allows()
            ->getItem('__test_stat_cache')
            ->andReturn($this->statCacheItem);
    }

    public function tearDown(): void
    {
        $this->codeShift->uninstall();
    }

    public function testRealpathCacheCanEmulateNonExistentFiles(): void
    {
        $actualPath = __DIR__ . '/Fixtures/my_actual_file.php';
        $imaginaryPath = __DIR__ . '/Fixtures/my_imaginary_file.php';
        $this->realpathCacheItem->allows()
            ->get()
            ->andReturn([
                $imaginaryPath => [
                    'realpath' => $actualPath,
                ]
            ]);
        $this->codeShift->shift(new FsCacheShiftSpec($this->codeShift, $this->cachePool, '__test_'));

        $result = include $imaginaryPath;

        static::assertSame('my imaginary result', $result);
    }

    public function testRealpathCacheCanPretendAnActualFileDoesNotExist(): void
    {
        $actualPath = __DIR__ . '/Fixtures/my_actual_file.php';
        $this->realpathCacheItem->allows()
            ->get()
            ->andReturn([
                $actualPath => [
                    'exists' => false,
                ]
            ]);
        $this->codeShift->shift(new FsCacheShiftSpec($this->codeShift, $this->cachePool, '__test_'));

        static::assertFalse(file_exists($actualPath));
        static::assertFalse(is_file($actualPath));
        static::assertFalse(is_dir($actualPath));
    }
}
