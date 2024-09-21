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

namespace Nytris\Boost\Tests\Functional\VirtualFilesystemMode\FilesystemFunctions;

use Asmblah\PhpCodeShift\Shifter\Filter\FileFilter;
use Nytris\Boost\Boost;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\Contents\SinglePoolContentsCache;
use Nytris\Boost\Tests\Functional\AbstractFunctionalTestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Class CopyTest.
 *
 * Tests the behaviour of the `copy(...)` built-in function
 * with Nytris Boost in virtual filesystem mode.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class CopyTest extends AbstractFunctionalTestCase
{
    private Boost $boost;
    private ContentsCacheInterface $contentsCache;
    private CacheItemPoolInterface $realpathCachePool;
    private CacheItemPoolInterface $statCachePool;
    private string $varPath;

    public function setUp(): void
    {
        $this->contentsCache = new SinglePoolContentsCache(new ArrayAdapter());
        $this->realpathCachePool = new ArrayAdapter();
        $this->statCachePool = new ArrayAdapter();

        $this->varPath = dirname(__DIR__, 4) . '/var/test';
        @mkdir($this->varPath, recursive: true);

        $this->boost = new Boost(
            realpathCachePool: $this->realpathCachePool,
            statCachePool: $this->statCachePool,
            realpathCacheKey: '__my_realpath_cache',
            statCacheKey: '__my_stat_cache',
            contentsCache: $this->contentsCache,
            // Avoid affecting test harness filesystem access, e.g. when autoloading Mockery classes.
            pathFilter: new FileFilter('/my/**'),
            asVirtualFilesystem: true
        );
    }

    public function tearDown(): void
    {
        $this->boost->uninstall();

        $this->rimrafDescendantsOf($this->varPath);
    }

    public function testCanCopyAnExistingFileFromAndToVirtualFilesystem(): void
    {
        $sourcePath = '/my/virtual/source_file.txt';
        $destinationPath = '/my/virtual/dest_file.txt';
        $this->boost->install();
        file_put_contents($sourcePath, 'my file contents');

        static::assertTrue(copy($sourcePath, $destinationPath));
        static::assertTrue(is_file($sourcePath));
        static::assertTrue(is_file($destinationPath));
        static::assertSame('my file contents', file_get_contents($destinationPath));
    }

    public function testCanCopyAnExistingFileFromOutsideToInsideVirtualFilesystem(): void
    {
        $sourcePath = $this->varPath . '/source_file.txt';
        $destinationPath = '/my/virtual/dest_file.txt';
        file_put_contents($sourcePath, 'my file contents');
        $this->boost->install();

        static::assertTrue(copy($sourcePath, $destinationPath));
        static::assertTrue(is_file($sourcePath));
        static::assertTrue(is_file($destinationPath));
        static::assertSame('my file contents', file_get_contents($destinationPath));
    }

    public function testCanCopyAnExistingFileFromInsideToOutsideVirtualFilesystem(): void
    {
        $sourcePath = '/my/virtual/source_file.txt';
        $destinationPath = $this->varPath . '/dest_file.txt';
        $this->boost->install();
        file_put_contents($sourcePath, 'my file contents');

        static::assertTrue(copy($sourcePath, $destinationPath));
        static::assertTrue(is_file($sourcePath));
        static::assertTrue(is_file($destinationPath));
        static::assertSame('my file contents', file_get_contents($destinationPath));
    }

    public function testReturnsFalseForANonExistentSourceFileInVirtualFilesystem(): void
    {
        $sourcePath = '/my/virtual/non_existent_source_file.txt';
        $destinationPath = '/my/virtual/dest_file.txt';
        $this->boost->install();

        static::assertFalse(@copy($sourcePath, $destinationPath));
        static::assertFalse(file_exists($destinationPath));
    }
}
