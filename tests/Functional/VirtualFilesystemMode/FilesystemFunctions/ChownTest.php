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
 * Class ChownTest.
 *
 * Tests the behaviour of the `chown(...)` built-in function
 * with Nytris Boost in virtual filesystem mode.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ChownTest extends AbstractFunctionalTestCase
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
            'getCwd' => '/my/cwd',
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

    public function testCanChangeOwnerOfAFileByUid(): void
    {
        $this->boost->install();
        file_put_contents('/my/virtual/file.txt', 'my file contents');

        static::assertTrue(chown('/my/virtual/file.txt', 123));
        $stat = stat('/my/virtual/file.txt');
        static::assertSame(123, $stat['uid']);
        static::assertSame(123, $stat[4]);
    }

    public function testCanChangeOwnerOfAFileByUsername(): void
    {
        $this->environment->allows()
            ->getUserIdFromName('myuser')
            ->andReturn(123);
        $this->boost->install();
        file_put_contents('/my/virtual/file.txt', 'my file contents');

        static::assertTrue(chown('/my/virtual/file.txt', 'myuser'));
        $stat = stat('/my/virtual/file.txt');
        static::assertSame(123, $stat['uid']);
        static::assertSame(123, $stat[4]);
    }

    public function testCanChangeOwnerOfADirectoryByUid(): void
    {
        $this->boost->install();
        mkdir('/my/virtual/dir', recursive: true);

        static::assertTrue(chown('/my/virtual/dir', 456));
        $stat = stat('/my/virtual/dir');
        static::assertSame(456, $stat['uid']);
        static::assertSame(456, $stat[4]);
    }

    public function testCanChangeOwnerOfADirectoryByUsername(): void
    {
        $this->environment->allows()
            ->getUserIdFromName('youruser')
            ->andReturn(456);
        $this->boost->install();
        mkdir('/my/virtual/dir', recursive: true);

        static::assertTrue(chown('/my/virtual/dir', 'youruser'));
        $stat = stat('/my/virtual/dir');
        static::assertSame(456, $stat['uid']);
        static::assertSame(456, $stat[4]);
    }

    public function testReturnsFalseForNonExistentFileWhenUidGiven(): void
    {
        $this->boost->install();

        static::assertFalse(chown('/my/virtual/non_existent_file.txt', 123));
    }

    public function testReturnsFalseForNonExistentFileWhenUsernameGiven(): void
    {
        $this->environment->allows()
            ->getUserIdFromName('myuser')
            ->andReturn(123);
        $this->boost->install();

        static::assertFalse(chown('/my/virtual/non_existent_file.txt', 'myuser'));
    }
}
