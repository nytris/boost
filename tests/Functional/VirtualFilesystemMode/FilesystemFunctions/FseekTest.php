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
 * Class FseekTest.
 *
 * Tests the behaviour of the `fseek(...)` built-in function
 * with Nytris Boost in virtual filesystem mode.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class FseekTest extends AbstractFunctionalTestCase
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

    public function testCanSeekToASpecificOffsetWithinAFileStream(): void
    {
        $this->boost->install();
        file_put_contents('/my/virtual/file.txt', 'my file contents');
        $stream = fopen('/my/virtual/file.txt', 'rb+');

        static::assertSame(0, fseek($stream, 4));
        static::assertSame('ile contents', fread($stream, 1024));
    }

    public function testCanSeekRelativeToCurrentPositionOfAFileStream(): void
    {
        $this->boost->install();
        file_put_contents('/my/virtual/file.txt', 'my file contents');
        $stream = fopen('/my/virtual/file.txt', 'rb+');
        fseek($stream, 4);

        static::assertSame(0, fseek($stream, 3, SEEK_CUR));
        static::assertSame(' contents', fread($stream, 1024));
    }

    public function testCanSeekRelativeToEndOfAFileStream(): void
    {
        $this->boost->install();
        file_put_contents('/my/virtual/file.txt', 'my file contents');
        $stream = fopen('/my/virtual/file.txt', 'rb+');

        static::assertSame(0, fseek($stream, -5, SEEK_END));
        static::assertSame('tents', fread($stream, 1024));
    }
}
