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
 * Class RealpathCachingTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class RealpathCachingTest extends AbstractFunctionalTestCase
{
    private Boost $boost;
    private MockInterface&CacheItemInterface $realpathCacheItem;
    private MockInterface&CacheItemPoolInterface $realpathCachePool;
    private MockInterface&CacheItemInterface $statCacheItemForIncludes;
    private MockInterface&CacheItemInterface $statCacheItemForNonIncludes;
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

        $this->varPath = dirname(__DIR__, 2) . '/var/test';
        @mkdir($this->varPath, recursive: true);

        $this->boost = new Boost(
            realpathCachePool: $this->realpathCachePool,
            statCachePool: $this->statCachePool,
            realpathCacheKey: '__my_realpath_cache',
            statCacheKey: '__my_stat_cache'
        );

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
        $this->boost->uninstall();

        $this->rimrafDescendantsOf($this->varPath);
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
        $this->boost->install();

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
        $this->boost->install();

        static::assertFalse(file_exists($actualPath));
        static::assertFalse(is_file($actualPath));
        static::assertFalse(is_dir($actualPath));
    }

    public function testRealpathCacheIsPersistedOnDestructionWhenChangesMade(): void
    {
        $this->realpathCachePool->expects()
            ->saveDeferred($this->realpathCacheItem)
            ->once();

        $this->boost->install();
        file_put_contents($this->varPath . '/my_file.txt', 'my contents');
        $this->boost->uninstall();
    }

    public function testNonExistentFilesArePersistedInRealpathCacheWhenEnabled(): void
    {
        $actualPath = __DIR__ . '/Fixtures/my_actual_file.php';
        $nonExistentPath = $this->varPath . '/my_non_existent_file.txt';
        $this->realpathCacheItem->expects('set')
            ->once()
            ->andReturnUsing(function (mixed $data) use ($actualPath, $nonExistentPath) {
                static::assertEquals(
                    [
                        $actualPath => ['realpath' => $actualPath],
                        $nonExistentPath => ['exists' => false],
                    ],
                    $data
                );
            });
        $this->realpathCachePool->expects()
            ->saveDeferred($this->realpathCacheItem)
            ->once();

        $this->boost->install();
        is_file($actualPath);
        is_file($nonExistentPath);
        $this->boost->uninstall();
    }

    public function testNonExistentFilesAreNotPersistedInRealpathCacheWhenDisabled(): void
    {
        $this->boost = new Boost(
            realpathCachePool: $this->realpathCachePool,
            statCachePool: $this->statCachePool,
            realpathCacheKey: '__my_realpath_cache',
            statCacheKey: '__my_stat_cache',
            cacheNonExistentFiles: false // Disable caching of non-existent files.
        );
        $actualPath = __DIR__ . '/Fixtures/my_actual_file.php';
        $nonExistentPath = $this->varPath . '/my_non_existent_file.txt';
        $this->realpathCacheItem->expects('set')
            ->once()
            ->andReturnUsing(function (mixed $data) use ($actualPath) {
                static::assertEquals(
                    [
                        $actualPath => ['realpath' => $actualPath],
                        // Note that no entry is added for the non-existent file.
                    ],
                    $data
                );
            });
        $this->realpathCachePool->expects()
            ->saveDeferred($this->realpathCacheItem)
            ->once();

        $this->boost->install();
        is_file($actualPath);
        is_file($nonExistentPath);
        $this->boost->uninstall();
    }

    public function testRealpathCacheIsNotPersistedOnDestructionWhenNoChangesMade(): void
    {
        $this->realpathCachePool->expects()
            ->saveDeferred($this->realpathCacheItem)
            ->never();

        $this->boost->install();
        $this->boost->uninstall();
    }

    public function testRealpathCacheIsEffectivelyClearedForAllSymbolicSourcePaths(): void
    {
        $symbolicPath = $this->varPath . '/a/b/c/../../../my-dir';
        $canonicalPath = $this->varPath . '/my-dir';
        mkdir($this->varPath . '/a/b/c', recursive: true);
        $this->boost->install();
        // Cause cache to be populated with non-existence for this path containing symbols.
        is_dir($symbolicPath);

        // Then create the directory.
        mkdir($canonicalPath);

        static::assertTrue(is_dir($symbolicPath));
    }

    public function testRealpathCacheIsEffectivelyClearedForAllSymlinkSourcePaths(): void
    {
        $symbolicPath = $this->varPath . '/a/b/c/../../../my-symlink';
        $canonicalSymlinkPath = $this->varPath . '/my-symlink';
        $eventualPath = $this->varPath . '/my-dir';
        mkdir($this->varPath . '/a/b/c', recursive: true);
        symlink($eventualPath, $canonicalSymlinkPath);
        $this->boost->install();
        // Cause cache to be populated with non-existence for this path containing symbols.
        is_dir($symbolicPath);

        // Then create the directory.
        mkdir($eventualPath);

        static::assertTrue(is_dir($symbolicPath));
        static::assertTrue(is_dir($canonicalSymlinkPath));
        static::assertTrue(is_dir($eventualPath));
    }

    public function testRealpathCacheIsEffectivelyClearedForEventualPaths(): void
    {
        $symbolicPath = $this->varPath . '/a/b/c/../../../my-symlink';
        $canonicalSymlinkPath = $this->varPath . '/my-symlink';
        $eventualPath = $this->varPath . '/my-file';
        mkdir($this->varPath . '/a/b/c', recursive: true);
        symlink($eventualPath, $canonicalSymlinkPath);
        $this->boost->install();
        // Cause cache to be populated with non-existence for the eventual path/symlink target.
        is_file($eventualPath);

        // Then create the file.
        touch($canonicalSymlinkPath);

        static::assertTrue(is_file($eventualPath));
        static::assertTrue(is_file($symbolicPath));
        static::assertTrue(is_file($canonicalSymlinkPath));
    }
}
