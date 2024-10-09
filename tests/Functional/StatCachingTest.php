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

use Nytris\Boost\Boost;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Class StatCachingTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class StatCachingTest extends AbstractFunctionalTestCase
{
    private Boost $boost;
    private CacheItemPoolInterface $realpathCachePool;
    private CacheItemPoolInterface $statCachePool;
    private string $varPath;

    public function setUp(): void
    {
        $this->realpathCachePool = new ArrayAdapter();
        $this->statCachePool = new ArrayAdapter();

        $this->varPath = dirname(__DIR__, 2) . '/var/test';
        @mkdir($this->varPath, recursive: true);

        $this->boost = new Boost(
            realpathCachePool: $this->realpathCachePool,
            statCachePool: $this->statCachePool
        );
        $this->canonicaliser = $this->boost->getLibrary()->getCanonicaliser();
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
        $this->setRealpathPsrCacheItem($this->realpathCachePool, $imaginaryPath, [
            // Unlike the test above, the realpath cache has the imaginary path as the target.
            'realpath' => $imaginaryPath,
        ]);
        $this->setStatPsrCacheItem($this->statCachePool, $imaginaryPath, isInclude: false, value: $actualPathStat);
        $this->boost->install();

        static::assertEquals(stat($imaginaryPath), $actualPathStat);
        static::assertTrue(file_exists($imaginaryPath));
        static::assertTrue(is_file($imaginaryPath));
        static::assertFalse(is_dir($imaginaryPath));
    }
}
