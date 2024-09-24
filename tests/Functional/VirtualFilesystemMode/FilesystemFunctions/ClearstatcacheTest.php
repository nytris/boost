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
use Nytris\Boost\Tests\Functional\Fixtures\HookedLogic;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Class ClearstatcacheTest.
 *
 * Tests the behaviour of the `clearstatcache(...)` built-in function
 * with Nytris Boost in virtual filesystem mode.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ClearstatcacheTest extends AbstractFunctionalTestCase
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

    public function testDoesNotAffectVirtualFilesystem(): void
    {
        $this->boost->install();
        file_put_contents('/my/virtual/file.txt', 'my file contents');

        // See notes in HookedLogic for why `clearstatcache(...)` cannot be called directly here.
        (new HookedLogic())->callClearstatcache();

        // In virtual filesystem mode, the caches are the filesystem storage so should not be invalidated.
        static::assertTrue(is_file('/my/virtual/file.txt'));
    }
}