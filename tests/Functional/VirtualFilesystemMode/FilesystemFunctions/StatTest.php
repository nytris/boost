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
 * Class StatTest.
 *
 * Tests the behaviour of the `stat(...)` built-in function
 * with Nytris Boost in virtual filesystem mode.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class StatTest extends AbstractFunctionalTestCase
{
    private Boost $boost;
    private ContentsCacheInterface $contentsCache;
    private CacheItemPoolInterface $realpathCachePool;
    private CacheItemPoolInterface $statCachePool;

    public function setUp(): void
    {
        $this->contentsCache = new SinglePoolContentsCache(new ArrayAdapter());
        $this->realpathCachePool = new ArrayAdapter();
        $this->statCachePool = new ArrayAdapter();

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
    }

    public function testReturnsCorrectStatusOfFileInVirtualFilesystem(): void
    {
        $this->boost->install();
        file_put_contents('/my/virtual/file.txt', 'my file contents');

        $stat = stat('/my/virtual/file.txt');

        static::assertSame(0100777, $stat['mode']);
        static::assertSame(0100777, $stat[2]);
        static::assertSame(16, $stat['size']);
        static::assertSame(16, $stat[7]);
    }

    public function testReturnsCorrectStatusOfDirectoryInVirtualFilesystem(): void
    {
        $this->boost->install();
        mkdir('/my/virtual/dir', recursive: true);

        $stat = stat('/my/virtual/dir');

        static::assertSame(0040777, $stat['mode']);
        static::assertSame(0040777, $stat[2]);
        static::assertSame(0, $stat['size']);
        static::assertSame(0, $stat[7]);
    }

    public function testReturnsFalseForNonExistentFile(): void
    {
        $this->boost->install();

        static::assertFalse(@stat('/my/virtual/non_existent_file.txt'));
    }
}
