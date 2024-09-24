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
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

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
    private CacheItemPoolInterface $realpathCachePool;
    private CacheItemPoolInterface $statCachePool;
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
        $this->realpathCachePool = new ArrayAdapter();
        $this->statCachePool = new ArrayAdapter();

        $this->varPath = dirname(__DIR__, 2) . '/var/test';
        @mkdir($this->varPath, recursive: true);

        $this->boost = new Boost(
            realpathCachePool: $this->realpathCachePool,
            statCachePool: $this->statCachePool,
            contentsCache: $this->contentsCache,
            // Avoid affecting test harness filesystem access, e.g. when autoloading Mockery classes.
            pathFilter: new MultipleFilter([
                new FileFilter('/my/**'),
                new FileFilter(__DIR__ . '/Fixtures/**'),
            ])
        );
        $this->canonicaliser = $this->boost->getLibrary()->getCanonicaliser();

        $this->setRealpathPsrCacheItem($this->realpathCachePool, '/my/cached/file.txt', [
            'realpath' => '/my/cached/file.txt',
        ]);
        $this->setRealpathPsrCacheItem($this->realpathCachePool, '/my/cached/module.php', [
            'realpath' => '/my/cached/module.php',
        ]);

        $this->setStatPsrCacheItem($this->statCachePool, '/my/cached/file.txt', isInclude: false, value: [
            'mode' => 0644,
            'uid' => 123,
            'gid' => 456,
            'size' => strlen($this->cachedTextFile->getContents()),
            'atime' => 1725558253,
            'mtime' => 1725558253,
            'ctime' => 1725558253,
        ]);
        $this->setStatPsrCacheItem($this->statCachePool, '/my/cached/module.php', isInclude: false, value: [
            'mode' => 0654,
            'uid' => 321,
            'gid' => 654,
            'size' => strlen($this->cachedPhpFileForPlainRead->getContents()),
            'atime' => 1725558258,
            'mtime' => 1725558258,
            'ctime' => 1725558258,
        ]);

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
        $this->setRealpathPsrCacheItem($this->realpathCachePool, $imaginaryPath, [
            'realpath' => $actualPath,
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
        $imaginaryPath = '/my/imaginary/path/to/my_module.php';
        $actualPath = __DIR__ . '/Fixtures/my_actual_file.php';
        $this->setRealpathPsrCacheItem($this->realpathCachePool, $imaginaryPath, [
            'realpath' => $actualPath,
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
        $this->setRealpathPsrCacheItem($this->realpathCachePool, $imaginaryPath, [
            'realpath' => $actualPath,
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
