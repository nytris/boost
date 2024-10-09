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

namespace Nytris\Boost\Tests\Functional\VirtualFilesystemMode;

use Asmblah\PhpCodeShift\CodeShift;
use Asmblah\PhpCodeShift\CodeShiftInterface;
use Asmblah\PhpCodeShift\Shifter\Filter\FileFilter;
use Nytris\Boost\Boost;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\Contents\SinglePoolContentsCache;
use Nytris\Boost\Tests\Functional\AbstractFunctionalTestCase;
use Nytris\Boost\Tests\Functional\Fixtures\HookedLogic;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Class OpcacheTest.
 *
 * Tests the virtual filesystem behaviour in conjunction with OPcache.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class OpcacheTest extends AbstractFunctionalTestCase
{
    private Boost $boost;
    private CodeShiftInterface $codeShift;
    private ContentsCacheInterface $contentsCache;
    private CacheItemPoolInterface $realpathCachePool;
    private CacheItemPoolInterface $statCachePool;

    public function setUp(): void
    {
        if (
            !extension_loaded('Zend OPcache') ||
            !ini_get('opcache.enable') ||
            !ini_get('opcache.enable_cli')
        ) {
            $this->markTestSkipped('Zend OPcache is not installed.');
        }

        $this->codeShift = new CodeShift();
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
        $this->codeShift->uninstall();
    }

    public function testVirtualFileMayBeCompiledEditedAndRecompiled(): void
    {
        $path = '/my/path/to/virtual_file.php';
        $this->boost->install();
        file_put_contents($path, '<?php return "my first result";');

        static::assertTrue(opcache_compile_file($path));
        static::assertSame('my first result', include $path);
        static::assertTrue(opcache_is_script_cached($path));
        // See notes in HookedLogic for why `opcache_invalidate(...)` cannot be called directly here.
        static::assertTrue((new HookedLogic())->callOpcacheInvalidate($path, force: true));

        static::assertNotFalse(file_put_contents($path, '<?php return "my second result";'));
        static::assertTrue(opcache_compile_file($path));
        static::assertSame('my second result', include $path);
        static::assertTrue(opcache_is_script_cached($path));

        static::assertNotFalse(file_put_contents($path, '<?php return "my third result";'));
        // Due to no invalidation being performed, the second variant should remain cached.
        static::assertSame('my second result', include $path);
        static::assertTrue(opcache_is_script_cached($path));
    }
}
