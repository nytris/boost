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
 * Class ReaddirTest.
 *
 * Tests the behaviour of the readdir(...) built-in function with Nytris Boost.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ReaddirTest extends AbstractFilesystemFunctionalTestCase
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

    public function testReturnsFilesInDirectory(): void
    {
        $this->boost->install();
        $directoryHandle = opendir($this->varPath . '/my-dir');

        $filenames = [
            readdir($directoryHandle),
            readdir($directoryHandle),
            readdir($directoryHandle),
            readdir($directoryHandle),
        ];
        sort($filenames);

        static::assertEquals(
            [
                '.',
                '..',
                'file-1.txt',
                'file-2.txt',
            ],
            $filenames
        );
        static::assertFalse(readdir($directoryHandle));
    }

    public function testReturnsCachedListOfFilesInDirectory(): void
    {
        $dirPath = $this->varPath . '/my-dir';
        $this->boost->install();
        scandir($dirPath); // Populate the directory cache for the directory.

        // Delete the directory without using unlink(...) etc. as that will be hooked.
        static::assertSame('', exec('rm -rf ' . escapeshellarg($dirPath), result_code: $exitCode));
        static::assertSame(0, $exitCode);

        $directoryHandle = opendir($dirPath);
        static::assertIsResource($directoryHandle);

        $filenames = [
            readdir($directoryHandle),
            readdir($directoryHandle),
            readdir($directoryHandle),
            readdir($directoryHandle),
        ];
        sort($filenames);

        static::assertEquals(
            [
                '.',
                '..',
                'file-1.txt',
                'file-2.txt',
            ],
            $filenames
        );
        static::assertFalse(readdir($directoryHandle));
    }
}
