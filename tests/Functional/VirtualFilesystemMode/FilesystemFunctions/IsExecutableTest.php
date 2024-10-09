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
 * Class IsExecutableTest.
 *
 * Tests the behaviour of the `is_executable(...)` built-in function
 * with Nytris Boost in virtual filesystem mode.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class IsExecutableTest extends AbstractFunctionalTestCase
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

    public function testExistentExecutableFileIsExecutable(): void
    {
        $this->boost->install();
        file_put_contents('/my/virtual/file.sh', 'my file contents');
        chmod('/my/virtual/file.sh', 0777);

        static::assertTrue(is_executable('/my/virtual/file.sh'));
    }

    public function testExistentNonExecutableFileIsNotExecutable(): void
    {
        $this->boost->install();
        file_put_contents('/my/virtual/file.sh', 'my file contents');
        chmod('/my/virtual/file.sh', 0444);

        static::assertFalse(is_executable('/my/virtual/file.sh'));
    }

    public function testNonExistentFileIsNotExecutable(): void
    {
        $this->boost->install();

        static::assertFalse(is_executable('/my/virtual/file.txt'));
    }
}
