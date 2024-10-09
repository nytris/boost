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
 * Class FlockTest.
 *
 * Tests the behaviour of the `flock(...)` built-in function
 * with Nytris Boost in virtual filesystem mode.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class FlockTest extends AbstractFunctionalTestCase
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

    public function testLockingAFileForSharedReadingSucceeds(): void
    {
        $this->boost->install();
        $stream1 = fopen('/my/virtual/file.txt', 'rb+');
        fwrite($stream1, 'my file contents');
        fflush($stream1);

        static::assertTrue(flock($stream1, LOCK_SH));
        $stream2 = fopen('/my/virtual/file.txt', 'rb+');
        static::assertTrue(flock($stream1, LOCK_SH));
        static::assertSame('my file contents', fread($stream2, 1024));
    }

    public function testLockingAFileForExclusiveWritingSucceeds(): void
    {
        $this->boost->install();
        $stream = fopen('/my/virtual/file.txt', 'rb+');
        fwrite($stream, 'my file contents');
        fflush($stream);
        rewind($stream);

        static::assertTrue(flock($stream, LOCK_EX));
        static::assertSame('my file contents', fread($stream, 1024));
    }
}
