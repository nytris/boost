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
use Mockery\MockInterface;
use Nytris\Boost\Boost;
use Nytris\Boost\Environment\EnvironmentInterface;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\Contents\SinglePoolContentsCache;
use Nytris\Boost\Library\Library;
use Nytris\Boost\Tests\Functional\AbstractFunctionalTestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Class TouchTest.
 *
 * Tests the behaviour of the `touch(...)` built-in function
 * with Nytris Boost in virtual filesystem mode.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class TouchTest extends AbstractFunctionalTestCase
{
    private Boost $boost;
    private ContentsCacheInterface $contentsCache;
    private MockInterface&EnvironmentInterface $environment;
    private CacheItemPoolInterface $realpathCachePool;
    private CacheItemPoolInterface $statCachePool;

    public function setUp(): void
    {
        $this->contentsCache = new SinglePoolContentsCache(new ArrayAdapter());
        $this->environment = mock(EnvironmentInterface::class, [
            'getCwd' => '/my/virtual/cwd',
            'getStartTime' => 1726739286,
        ]);
        $this->realpathCachePool = new ArrayAdapter();
        $this->statCachePool = new ArrayAdapter();

        // Load Mockery internals for the stub as there is a catch-22 with the path filtering.
        $this->environment->getCwd();

        $this->boost = new Boost(
            library: new Library($this->environment),
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

    public function testCanCorrectlyChangeModificationTimestampOfAFileInVirtualFilesystem(): void
    {
        $this->boost->install();
        file_put_contents('/my/virtual/file.txt', 'my file contents');

        static::assertTrue(touch('/my/virtual/file.txt', mtime: 1726739486));
        $stat = stat('/my/virtual/file.txt');
        static::assertSame(1726739486, $stat['mtime']);
        static::assertSame(1726739486, $stat[9]);
    }

    public function testCanCorrectlyTouchWithNeitherModificationNorAccessTimestampsSpecified(): void
    {
        $this->environment->allows()
            ->getStartTime()
            ->andReturn(1726739986);
        $this->environment->allows()
            ->getTime()
            ->andReturn(1726749987);
        $this->boost->install();
        file_put_contents('/my/virtual/file.txt', 'my file contents');

        static::assertTrue(touch('/my/virtual/file.txt'));
        $stat = stat('/my/virtual/file.txt');
        static::assertSame(1726749987, $stat['mtime'], 'Should use current system time');
        static::assertSame(1726749987, $stat[9]);
        static::assertSame(1726749987, $stat['atime'], 'Should use current system time');
        static::assertSame(1726749987, $stat[8]);
    }

    public function testCanCorrectlyTouchWithOnlyModificationTimestampSpecified(): void
    {
        $this->environment->allows()
            ->getStartTime()
            ->andReturn(1726739986);
        $this->boost->install();
        file_put_contents('/my/virtual/file.txt', 'my file contents');

        static::assertTrue(touch('/my/virtual/file.txt', mtime: 1726749987));
        $stat = stat('/my/virtual/file.txt');
        static::assertSame(1726749987, $stat['mtime']);
        static::assertSame(1726749987, $stat[9]);
        static::assertSame(1726749987, $stat['atime']);
        static::assertSame(1726749987, $stat[8]);
    }

    public function testCanCorrectlyChangeModificationTimestampOfADirectoryInVirtualFilesystem(): void
    {
        $this->boost->install();
        mkdir('/my/virtual/dir', recursive: true);

        static::assertTrue(touch('/my/virtual/dir', mtime: 1726739486));
        $stat = stat('/my/virtual/dir');
        static::assertSame(1726739486, $stat['mtime']);
        static::assertSame(1726739486, $stat[9]);
    }

    public function testCanCorrectlyChangeAccessTimestampOfAFileInVirtualFilesystem(): void
    {
        $this->boost->install();
        file_put_contents('/my/virtual/file.txt', 'my file contents');

        /** @noinspection PotentialMalwareInspection */
        static::assertTrue(touch('/my/virtual/file.txt', mtime: 1726739386, atime: 1726739486));
        $stat = stat('/my/virtual/file.txt');
        static::assertSame(1726739486, $stat['atime']);
        static::assertSame(1726739486, $stat[8]);
    }

    public function testCanCorrectlyChangeAccessTimestampOfADirectoryInVirtualFilesystem(): void
    {
        $this->boost->install();
        mkdir('/my/virtual/dir', recursive: true);

        /** @noinspection PotentialMalwareInspection */
        static::assertTrue(touch('/my/virtual/dir', mtime: 1726739386, atime: 1726739486));
        $stat = stat('/my/virtual/dir');
        static::assertSame(1726739486, $stat['atime']);
        static::assertSame(1726739486, $stat[8]);
    }

    public function testReturnsFalseForNonExistentFile(): void
    {
        $this->boost->install();

        static::assertFalse(@touch('/my/virtual/non_existent_file.txt'));
    }
}
