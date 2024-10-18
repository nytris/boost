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

namespace Nytris\Boost\Tests\Functional\FsCache\FilesystemFunctions;

use Nytris\Boost\Boost;
use Nytris\Boost\FsCache\Directory\SinglePoolDirectoryCache;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Class ScandirTest.
 *
 * Tests the behaviour of the scandir(...) built-in function with Nytris Boost.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ScandirTest extends AbstractFilesystemFunctionalTestCase
{
    private CacheItemPoolInterface $directoryCachePool;

    public function setUp(): void
    {
        parent::setUp();

        mkdir($this->varPath . '/my-dir');
        file_put_contents($this->varPath . '/my-dir/file-1.txt', 'first');
        file_put_contents($this->varPath . '/my-dir/file-2.txt', 'second');

        $this->directoryCachePool = new ArrayAdapter();

        $this->boost = new Boost(
            realpathCachePool: $this->realpathCachePool,
            statCachePool: $this->statCachePool,
            directoryCache: new SinglePoolDirectoryCache($this->directoryCachePool)
        );
    }

    public function testReturnsFilesInDirectorySortedAlphabeticallyAscendingByDefault(): void
    {
        $this->boost->install();

        static::assertEquals(
            [
                '.',
                '..',
                'file-1.txt',
                'file-2.txt',
            ],
            scandir($this->varPath . '/my-dir')
        );
    }

    public function testReturnsFilesInDirectorySortedAlphabeticallyDescendingWhenSpecified(): void
    {
        $this->boost->install();

        static::assertEquals(
            [
                'file-2.txt',
                'file-1.txt',
                '..',
                '.',
            ],
            scandir($this->varPath . '/my-dir', sorting_order: SCANDIR_SORT_DESCENDING)
        );
    }

    public function testReturnsCachedListOfFilesInDirectorySortedAlphabeticallyAscendingByDefault(): void
    {
        $dirPath = $this->varPath . '/my-dir';
        $this->boost->install();
        scandir($dirPath); // Populate the directory cache for the directory.

        // Delete the directory without using unlink(...) etc. as that will be hooked.
        static::assertSame('', exec('rm -rf ' . escapeshellarg($dirPath), result_code: $exitCode));
        static::assertSame(0, $exitCode);

        static::assertEquals(
            [
                '.',
                '..',
                'file-1.txt',
                'file-2.txt',
            ],
            scandir($dirPath)
        );
    }

    public function testReturnsCachedListOfFilesInDirectorySortedAlphabeticallyDescendingWhenSpecified(): void
    {
        $dirPath = $this->varPath . '/my-dir';
        $this->boost->install();
        scandir($dirPath); // Populate the directory cache for the directory.

        // Delete the directory without using unlink(...) etc. as that will be hooked.
        static::assertSame('', exec('rm -rf ' . escapeshellarg($dirPath), result_code: $exitCode));
        static::assertSame(0, $exitCode);

        static::assertEquals(
            [
                'file-2.txt',
                'file-1.txt',
                '..',
                '.',
            ],
            scandir($dirPath, sorting_order: SCANDIR_SORT_DESCENDING)
        );
    }
}
