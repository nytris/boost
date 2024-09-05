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

namespace Nytris\Boost\Tests\Unit;

use Nytris\Boost\BoostPackage;
use Nytris\Boost\Charge;
use Nytris\Boost\Tests\AbstractTestCase;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class BoostPackageTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class BoostPackageTest extends AbstractTestCase
{
    public function testGetPackageFacadeFqcnReturnsCorrectFqcn(): void
    {
        $package = new BoostPackage();

        static::assertSame(Charge::class, $package->getPackageFacadeFqcn());
    }

    public function testGetRealpathCacheKeyReturnsGivenKey(): void
    {
        $package = new BoostPackage(realpathCacheKey: 'my_realpath_key');

        static::assertSame('my_realpath_key', $package->getRealpathCacheKey());
    }

    public function testGetRealpathCachePoolReturnsCallbackResultWhenFactoryIsGiven(): void
    {
        $cachePool = mock(CacheItemPoolInterface::class);
        $capturedCachePath = null;
        $package = new BoostPackage(
            realpathCachePoolFactory: function (string $boostCachePath) use (
                $cachePool,
                &$capturedCachePath
            ) {
                $capturedCachePath = $boostCachePath;

                return $cachePool;
            }
        );

        static::assertSame($cachePool, $package->getRealpathCachePool('/my/cache/path'));
        static::assertSame('/my/cache/path', $capturedCachePath);
    }

    public function testGetRealpathCachePoolReturnsNullWhenFactoryIsNull(): void
    {
        $package = new BoostPackage(realpathCachePoolFactory: null);

        static::assertNull($package->getRealpathCachePool('/my/cache/path'));
    }

    public function testGetStatCacheKeyReturnsGivenKey(): void
    {
        $package = new BoostPackage(statCacheKey: 'my_stat_key');

        static::assertSame('my_stat_key', $package->getStatCacheKey());
    }

    public function testGetStatCachePoolReturnsCallbackResultWhenFactoryIsGiven(): void
    {
        $cachePool = mock(CacheItemPoolInterface::class);
        $capturedCachePath = null;
        $package = new BoostPackage(
            statCachePoolFactory: function (string $boostCachePath) use (
                $cachePool,
                &$capturedCachePath
            ) {
                $capturedCachePath = $boostCachePath;

                return $cachePool;
            }
        );

        static::assertSame($cachePool, $package->getStatCachePool('/my/cache/path'));
        static::assertSame('/my/cache/path', $capturedCachePath);
    }

    public function testGetStatCachePoolReturnsNullWhenFactoryIsNull(): void
    {
        $package = new BoostPackage(statCachePoolFactory: null);

        static::assertNull($package->getStatCachePool('/my/cache/path'));
    }

    public function testShouldCacheNonExistentFilesReturnsTrueWhenSet(): void
    {
        $package = new BoostPackage(cacheNonExistentFiles: true);

        static::assertTrue($package->shouldCacheNonExistentFiles());
    }

    public function testShouldCacheNonExistentFilesReturnsFalseWhenSet(): void
    {
        $package = new BoostPackage(cacheNonExistentFiles: false);

        static::assertFalse($package->shouldCacheNonExistentFiles());
    }

    public function testShouldHookBuiltinFunctionsReturnsTrueWhenSet(): void
    {
        $package = new BoostPackage(hookBuiltinFunctions: true);

        static::assertTrue($package->shouldHookBuiltinFunctions());
    }

    public function testShouldHookBuiltinFunctionsReturnsFalseWhenSet(): void
    {
        $package = new BoostPackage(hookBuiltinFunctions: false);

        static::assertFalse($package->shouldHookBuiltinFunctions());
    }
}
