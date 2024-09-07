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
use Mockery\MockInterface;
use Nytris\Boost\Boost;
use Nytris\Boost\FsCache\Contents\CachedFileInterface;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class ContentsCachingTest.
 *
 * Tests the file contents caching behaviour of the filesystem cache.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ContentsCachingTest extends AbstractFunctionalTestCase
{
    private Boost $boost;
    private MockInterface&CachedFileInterface $cachedPhpFileForIncludeRead;
    private MockInterface&CachedFileInterface $cachedPhpFileForPlainRead;
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
        $this->cachedTextFile = mock(CachedFileInterface::class, [
            'getContents' => 'my file contents',
            'isCached' => true,
        ]);
        $this->cachedPhpFileForPlainRead = mock(CachedFileInterface::class, [
            'getContents' => '<?php return "my result from original code";',
            'isCached' => true,
        ]);
        $this->cachedPhpFileForIncludeRead = mock(CachedFileInterface::class, [
            'getContents' => '<?php return "my result from shifted code";',
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
                '/my/cached/module.php' => ['realpath' => '/my/cached/module.php'],
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
                '/my/cached/file.txt' => [
                    'mode' => 0644,
                    'uid' => 123,
                    'gid' => 456,
                    'size' => strlen($this->cachedTextFile->getContents()),
                    'atime' => 1725558253,
                    'mtime' => 1725558253,
                    'ctime' => 1725558253,
                ],
                '/my/cached/module.php' => [
                    'mode' => 0654,
                    'uid' => 321,
                    'gid' => 654,
                    'size' => strlen($this->cachedPhpFileForPlainRead->getContents()),
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
            pathFilter: new FileFilter('/my/**')
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
            ->getItemForPath('/my/cached/module.php', true)
            ->andReturn($this->cachedPhpFileForIncludeRead)
            ->byDefault();
        $this->contentsCache->allows()
            ->getItemForPath('/my/cached/module.php', false)
            ->andReturn($this->cachedPhpFileForPlainRead)
            ->byDefault();
    }

    public function tearDown(): void
    {
        $this->boost->uninstall();

        $this->rimrafDescendantsOf($this->varPath);
    }

    public function testPlainReadsHitPlainContentsCacheWhenEnabled(): void
    {
        $this->boost->install();

        static::assertSame('my file contents', file_get_contents('/my/cached/file.txt'));
    }

    public function testIncludeReadsOfPhpModulesHitIncludeContentsCacheWhenEnabled(): void
    {
        $path = '/my/cached/module.php';
        $this->boost->install();

        static::assertSame('my result from shifted code', include $path);
    }

    public function testPlainReadsOfPhpModulesHitPlainContentsCacheWhenEnabled(): void
    {
        $this->boost->install();

        static::assertSame(
            '<?php return "my result from original code";',
            file_get_contents('/my/cached/module.php')
        );
    }

    public function testPlainReadMissesAreCachedInPlainContentsCacheWhenEnabled(): void
    {
        $textFile = mock(CachedFileInterface::class, [
            'isCached' => false,
        ]);
        $imaginaryPath = '/my/path/to/my_file.txt';
        $actualPath = __DIR__ . '/Fixtures/my_actual_file.txt';
        $this->realpathCacheItem->allows()
            ->get()
            ->andReturn([
                $imaginaryPath => ['realpath' => $actualPath],
            ]);
        $this->boost->install();

        $this->contentsCache->expects()
            ->getItemForPath($actualPath, false)
            ->once()
            ->andReturn($textFile);
        $textFile->expects()
            ->setContents("Hello from my file!\n")
            ->once();

        static::assertSame("Hello from my file!\n", file_get_contents($imaginaryPath));
    }

    public function testIncludeReadMissesOfPhpModulesAreCachedInIncludeContentsCacheWhenEnabled(): void
    {
        $phpFile = mock(CachedFileInterface::class, [
            'isCached' => false,
        ]);
        $imaginaryPath = '/my/path/to/my_module.php';
        $actualPath = __DIR__ . '/Fixtures/my_actual_file.php';
        $this->realpathCacheItem->allows()
            ->get()
            ->andReturn([
                $imaginaryPath => ['realpath' => $actualPath],
            ]);
        $this->boost->install();

        $this->contentsCache->expects()
            ->getItemForPath($actualPath, true)
            ->once()
            ->andReturn($phpFile);
        $phpFile->expects()
            ->setContents("<?php\n\nreturn 'my imaginary result';\n")
            ->once();

        static::assertSame('my imaginary result', include $imaginaryPath);
    }

    public function testPlainReadMissesOfPhpModulesAreCachedInPlainContentsCacheWhenEnabled(): void
    {
        $phpFile = mock(CachedFileInterface::class, [
            'isCached' => false,
        ]);
        $imaginaryPath = '/my/path/to/my_module.php';
        $actualPath = __DIR__ . '/Fixtures/my_actual_file.php';
        $this->realpathCacheItem->allows()
            ->get()
            ->andReturn([
                $imaginaryPath => ['realpath' => $actualPath],
            ]);
        $this->boost->install();

        $this->contentsCache->expects()
            ->getItemForPath($actualPath, false)
            ->once()
            ->andReturn($phpFile);
        $phpFile->expects()
            ->setContents("<?php\n\nreturn 'my imaginary result';\n")
            ->once();

        static::assertSame("<?php\n\nreturn 'my imaginary result';\n", file_get_contents($imaginaryPath));
    }
}
