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
 * Class RewinddirTest.
 *
 * Tests the behaviour of the rewinddir(...) built-in function with Nytris Boost.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class RewinddirTest extends AbstractFilesystemFunctionalTestCase
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

    public function testCanRewindDuringInitialDirectoryEnumeration(): void
    {
        $this->boost->install();
        $directoryHandle = opendir($this->varPath . '/my-dir');

        $firstFilenames = [
            readdir($directoryHandle),
            readdir($directoryHandle),
            readdir($directoryHandle),
        ];
        rewinddir($directoryHandle);
        $secondFilenames = [
            readdir($directoryHandle),
            readdir($directoryHandle),
            readdir($directoryHandle),
            readdir($directoryHandle),
        ];
        sort($secondFilenames);

        // Order returned will be underlying filesystem order, so we cannot guarantee the contents exactly
        // prior to the rewind.
        static::assertNotEmpty($firstFilenames);
        static::assertEquals($firstFilenames, array_intersect($firstFilenames, $secondFilenames));
        static::assertEquals(
            [
                '.',
                '..',
                'file-1.txt',
                'file-2.txt',
            ],
            $secondFilenames
        );
        static::assertFalse(readdir($directoryHandle));
    }

    public function testCanRewindDuringDirectoryEnumerationWhenCached(): void
    {
        $dirPath = $this->varPath . '/my-dir';
        $this->boost->install();
        scandir($dirPath); // Populate the directory cache for the directory.

        // Delete the directory without using unlink(...) etc. as that will be hooked.
        static::assertSame('', exec('rm -rf ' . escapeshellarg($dirPath), result_code: $exitCode));
        static::assertSame(0, $exitCode);

        $directoryHandle = opendir($dirPath);

        $firstFilenames = [
            readdir($directoryHandle),
            readdir($directoryHandle),
            readdir($directoryHandle),
        ];
        rewinddir($directoryHandle);
        $secondFilenames = [
            readdir($directoryHandle),
            readdir($directoryHandle),
            readdir($directoryHandle),
            readdir($directoryHandle),
        ];
        sort($secondFilenames);

        // Order returned will be underlying filesystem order, so we cannot guarantee the contents exactly
        // prior to the rewind.
        static::assertNotEmpty($firstFilenames);
        static::assertEquals($firstFilenames, array_intersect($firstFilenames, $secondFilenames));
        static::assertEquals(
            [
                '.',
                '..',
                'file-1.txt',
                'file-2.txt',
            ],
            $secondFilenames
        );
        static::assertFalse(readdir($directoryHandle));
    }
}
