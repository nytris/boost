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

namespace Nytris\Boost\Tests\Functional;

use Asmblah\PhpCodeShift\Shifter\Filter\FileFilter;
use Asmblah\PhpCodeShift\Shifter\Filter\MultipleFilter;
use Mockery\MockInterface;
use Nytris\Boost\Boost;
use Nytris\Boost\FsCache\Contents\CachedFileInterface;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class OpcacheTest.
 *
 * Tests the filesystem caching behaviour in conjunction with OPcache.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class OpcacheTest extends AbstractFunctionalTestCase
{
    private Boost $boost;
    private MockInterface&CachedFileInterface $cachedActualPhpFileForIncludeRead;
    private MockInterface&CachedFileInterface $cachedActualPhpFileForPlainRead;
    private MockInterface&CachedFileInterface $cachedEmulatedPhpFileForIncludeRead;
    private MockInterface&CachedFileInterface $cachedEmulatedPhpFileForPlainRead;
    private MockInterface&CachedFileInterface $cachedTextFile;
    private MockInterface&ContentsCacheInterface $contentsCache;
    private MockInterface&CacheItemInterface $realpathCacheItem;
    private MockInterface&CacheItemPoolInterface $realpathCachePool;
    private MockInterface&CacheItemInterface $statCacheItemForIncludes;
    private MockInterface&CacheItemInterface $statCacheItemForNonIncludes;
    private MockInterface&CacheItemPoolInterface $statCachePool;
    private string $varPath;

    public function setUp(): void
    {
        if (
            !extension_loaded('Zend OPcache') ||
            !ini_get('opcache.enable') ||
            !ini_get('opcache.enable_cli')
        ) {
            $this->markTestSkipped('Zend OPcache is not installed.');
        }

        $this->cachedTextFile = mock(CachedFileInterface::class, [
            'getContents' => 'my file contents',
            'isCached' => true,
        ]);
        $this->cachedActualPhpFileForPlainRead = mock(CachedFileInterface::class, [
            'getContents' => '<?php return "my result from original code";',
            'isCached' => true,
        ]);
        $this->cachedActualPhpFileForIncludeRead = mock(CachedFileInterface::class, [
            'getContents' => '<?php return "my result from shifted code";',
            'isCached' => true,
        ]);
        $this->cachedEmulatedPhpFileForPlainRead = mock(CachedFileInterface::class, [
            'getContents' => '<?php return "my result from emulated original code";',
            'isCached' => true,
        ]);
        $this->cachedEmulatedPhpFileForIncludeRead = mock(CachedFileInterface::class, [
            'getContents' => '<?php return "my result from shifted emulated code";',
            'isCached' => true,
        ]);
        $this->contentsCache = mock(ContentsCacheInterface::class);
        $this->realpathCachePool = mock(CacheItemPoolInterface::class, [
            'saveDeferred' => null,
        ]);
        $this->statCachePool = mock(CacheItemPoolInterface::class, [
            'saveDeferred' => null,
        ]);
        $this->realpathCacheItem = mock(CacheItemInterface::class, [
            'get' => [
                '/my/cached/file.txt' => ['realpath' => '/my/cached/file.txt'],
                '/my/emulated/cached/module.php' => ['realpath' => '/my/emulated/cached/module.php'],
                __DIR__ . '/Fixtures/my_actual_file.php' => [
                    'realpath' => __DIR__ . '/Fixtures/my_actual_file.php'
                ],
            ],
            'isHit' => true,
            'set' => null,
        ]);
        $this->statCacheItemForIncludes = mock(CacheItemInterface::class, [
            'get' => [],
            'isHit' => true,
            'set' => null,
        ]);
        $this->statCacheItemForNonIncludes = mock(CacheItemInterface::class, [
            'get' => [
                '/my/emulated/cached/module.php' => [
                    'mode' => 0654,
                    'uid' => 321,
                    'gid' => 654,
                    'size' => strlen($this->cachedEmulatedPhpFileForPlainRead->getContents()),
                    'atime' => 1725558258,
                    'mtime' => 1725558258,
                    'ctime' => 1725558258,
                ],
            ],
            'isHit' => true,
            'set' => null,
        ]);

        $this->varPath = dirname(__DIR__, 2) . '/var/test';
        @mkdir($this->varPath, recursive: true);

        $this->boost = new Boost(
            realpathCachePool: $this->realpathCachePool,
            statCachePool: $this->statCachePool,
            realpathCacheKey: '__my_realpath_cache',
            statCacheKey: '__my_stat_cache',
            contentsCache: $this->contentsCache,
            // Avoid affecting test harness filesystem access, e.g. when autoloading Mockery classes.
            pathFilter: new MultipleFilter([
                new FileFilter('/my/**'),
                new FileFilter(dirname(__DIR__) . '/**'),
            ])
        );

        $this->realpathCachePool->allows()
            ->getItem('__my_realpath_cache')
            ->andReturn($this->realpathCacheItem)
            ->byDefault();
        $this->statCachePool->allows()
            ->getItem('__my_stat_cache_includes')
            ->andReturn($this->statCacheItemForIncludes)
            ->byDefault();
        $this->statCachePool->allows()
            ->getItem('__my_stat_cache_plain')
            ->andReturn($this->statCacheItemForNonIncludes)
            ->byDefault();

        $this->contentsCache->allows()
            ->getItemForPath('/my/cached/file.txt', false)
            ->andReturn($this->cachedTextFile)
            ->byDefault();
        $this->contentsCache->allows()
            ->getItemForPath(__DIR__ . '/Fixtures/my_actual_file.php', true)
            ->andReturn($this->cachedActualPhpFileForIncludeRead)
            ->byDefault();
        $this->contentsCache->allows()
            ->getItemForPath(__DIR__ . '/Fixtures/my_actual_file.php', false)
            ->andReturn($this->cachedActualPhpFileForPlainRead)
            ->byDefault();
        $this->contentsCache->allows()
            ->getItemForPath('/my/emulated/cached/module.php', true)
            ->andReturn($this->cachedEmulatedPhpFileForIncludeRead)
            ->byDefault();
        $this->contentsCache->allows()
            ->getItemForPath('/my/emulated/cached/module.php', false)
            ->andReturn($this->cachedEmulatedPhpFileForPlainRead)
            ->byDefault();
    }

    public function tearDown(): void
    {
        $this->boost->uninstall();

        $this->rimrafDescendantsOf($this->varPath);
    }

    public function testOpcacheUsesIncludeContentsCacheForAnActualFileWhenEnabled(): void
    {
        $path = __DIR__ . '/Fixtures/my_actual_file.php';
        $this->boost->install();

        static::assertTrue(opcache_compile_file($path));
        static::assertSame('my result from shifted code', include $path);
        static::assertTrue(opcache_is_script_cached($path));
    }

    public function testOpcacheUsesIncludeContentsCacheForAnEmulatedFileWhenEnabled(): void
    {
        $path = '/my/emulated/cached/module.php';
        $this->boost->install();

        static::assertTrue(opcache_compile_file($path));
        static::assertSame('my result from shifted emulated code', include $path);
        static::assertTrue(opcache_is_script_cached($path));
    }
}
