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
 * Class IsWritableTest.
 *
 * Tests the behaviour of the `is_writable(...)` built-in function
 * with Nytris Boost in virtual filesystem mode.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class IsWritableTest extends AbstractFunctionalTestCase
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

    public function testExistentWritableFileIsWritable(): void
    {
        $this->boost->install();
        file_put_contents('/my/virtual/file.txt', 'my file contents');

        static::assertTrue(is_writable('/my/virtual/file.txt'));
    }

    public function testExistentNonWritableFileIsNotWritable(): void
    {
        $this->boost->install();
        file_put_contents('/my/virtual/file.txt', 'my file contents');
        chmod('/my/virtual/file.txt', 0444);

        static::assertFalse(is_writable('/my/virtual/file.txt'));
    }

    public function testNonExistentFileIsNotWritable(): void
    {
        $this->boost->install();

        // Counter-intuitive, but this is the behaviour for normal filesystem access too.
        static::assertFalse(is_writable('/my/virtual/file.txt'));
    }
}
