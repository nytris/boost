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
 * Class RealpathCachingTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class RealpathCachingTest extends AbstractFunctionalTestCase
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

    public function testRealpathCacheCanEmulateNonExistentFiles(): void
    {
        $actualPath = __DIR__ . '/Fixtures/my_actual_file.php';
        $imaginaryPath = __DIR__ . '/Fixtures/my_imaginary_file.php';
        $this->setRealpathPsrCacheItem($this->realpathCachePool, $imaginaryPath, [
            'realpath' => $actualPath,
        ]);
        $this->boost->install();

        $result = include $imaginaryPath;

        static::assertSame('my imaginary result', $result);
    }

    public function testRealpathCacheCanPretendAnActualFileDoesNotExist(): void
    {
        $actualPath = __DIR__ . '/Fixtures/my_actual_file.php';
        $this->setRealpathPsrCacheItem($this->realpathCachePool, $actualPath, [
            'exists' => false,
        ]);
        $this->boost->install();

        static::assertFalse(file_exists($actualPath));
        static::assertFalse(is_file($actualPath));
        static::assertFalse(is_dir($actualPath));
    }

    public function testNonExistentFilesArePersistedInRealpathCacheWhenEnabled(): void
    {
        $actualPath = __DIR__ . '/Fixtures/my_actual_file.php';
        $nonExistentPath = $this->varPath . '/my_non_existent_file.txt';

        $this->boost->install();
        is_file($actualPath);
        is_file($nonExistentPath);
        $this->boost->uninstall();

        static::assertEquals(
            ['realpath' => $actualPath],
            $this->getRealpathPsrCacheItem($this->realpathCachePool, $actualPath)
        );
        static::assertEquals(
            ['exists' => false],
            $this->getRealpathPsrCacheItem($this->realpathCachePool, $nonExistentPath)
        );
    }

    public function testNonExistentFilesAreNotPersistedInRealpathCacheWhenDisabled(): void
    {
        $this->boost = new Boost(
            realpathCachePool: $this->realpathCachePool,
            statCachePool: $this->statCachePool,
            cacheNonExistentFiles: false // Disable caching of non-existent files.
        );
        $actualPath = __DIR__ . '/Fixtures/my_actual_file.php';
        $nonExistentPath = $this->varPath . '/my_non_existent_file.txt';

        $this->boost->install();
        is_file($actualPath);
        is_file($nonExistentPath);
        $this->boost->uninstall();

        static::assertEquals(
            ['realpath' => $actualPath],
            $this->getRealpathPsrCacheItem($this->realpathCachePool, $actualPath)
        );
        // Note that no entry is added for the non-existent file.
        static::assertNull($this->getRealpathPsrCacheItem($this->realpathCachePool, $nonExistentPath));
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
