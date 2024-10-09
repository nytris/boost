<?php

/*
 * Nytris Boost
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/nytris/boost/
 *
 * Released under the MIT license.
 * https://github.com/nytris/boost/raw/main/MIT-LICENSE.txt
 */

declare(strict_types=1);

namespace Nytris\Boost\Tests\Unit\FsCache\Stat;

use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapperInterface;
use LogicException;
use Mockery;
use Mockery\MockInterface;
use Nytris\Boost\Environment\EnvironmentInterface;
use Nytris\Boost\FsCache\CanonicaliserInterface;
use Nytris\Boost\FsCache\Realpath\RealpathCacheInterface;
use Nytris\Boost\FsCache\Stat\StatCache;
use Nytris\Boost\FsCache\Stat\StatCacheInterface;
use Nytris\Boost\Tests\AbstractTestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class StatCacheTest.
 *
 * @phpstan-import-type MultipleStatCacheStorage from StatCacheInterface
 * @phpstan-import-type StatCacheEntry from StatCacheInterface
 * @author Dan Phillimore <dan@ovms.co>
 */
class StatCacheTest extends AbstractTestCase
{
    private MockInterface&CanonicaliserInterface $canonicaliser;
    private MockInterface&EnvironmentInterface $environment;
    private MockInterface&RealpathCacheInterface $realpathCache;
    private StatCache $statCache;
    private MockInterface&CacheItemPoolInterface $statCachePool;
    private MockInterface&CacheItemInterface $statCachePoolItem;
    private MockInterface&StreamHandlerInterface $wrappedStreamHandler;

    public function setUp(): void
    {
        $this->canonicaliser = mock(CanonicaliserInterface::class);
        $this->environment = mock(EnvironmentInterface::class, [
            'getStartTime' => 1725558258,
        ]);
        $this->realpathCache = mock(RealpathCacheInterface::class);
        $this->statCachePool = mock(CacheItemPoolInterface::class);
        $this->statCachePoolItem = mock(CacheItemInterface::class);
        $this->wrappedStreamHandler = mock(StreamHandlerInterface::class);

        $this->canonicaliser->allows('canonicalise')
            ->andReturnArg(0)
            ->byDefault();
        $this->canonicaliser->allows('canonicaliseCacheKey')
            ->andReturnUsing(fn (string $key) => 'canonicalised--' . preg_replace('#\W#', '_', $key))
            ->byDefault();

        $this->wrappedStreamHandler->allows('unwrapped')
            ->andReturnUsing(fn (callable $callback) => $callback())
            ->byDefault();

        $this->statCache = new StatCache(
            $this->wrappedStreamHandler,
            $this->environment,
            $this->canonicaliser,
            $this->realpathCache,
            statPreloadCachePool: null,
            statCachePool: $this->statCachePool,
            asVirtualFilesystem: false
        );
    }

    public function testConstructorLoadsInMemoryCacheFromPreloadPoolWhenProvided(): void
    {
        $preloadCachePool = mock(CacheItemPoolInterface::class);
        $includePreloadItem = mock(CacheItemInterface::class, [
            'get' => [
                '/my/first/path' => ['size' => 124],
                '/my/second/path' => ['size' => 457],
            ],
            'isHit' => true,
        ]);
        $nonIncludePreloadItem = mock(CacheItemInterface::class, [
            'get' => [
                '/my/first/path' => ['size' => 123],
                '/my/second/path' => ['size' => 456],
            ],
            'isHit' => true,
        ]);

        $preloadCachePool->expects()
            ->getItem(StatCacheInterface::INCLUDE_PRELOAD_CACHE_KEY)
            ->andReturn($includePreloadItem)
            ->once();
        $preloadCachePool->expects()
            ->getItem(StatCacheInterface::NON_INCLUDE_PRELOAD_CACHE_KEY)
            ->andReturn($nonIncludePreloadItem)
            ->once();

        $this->statCache = new StatCache(
            $this->wrappedStreamHandler,
            $this->environment,
            $this->canonicaliser,
            $this->realpathCache,
            statPreloadCachePool: $preloadCachePool,
            statCachePool: $this->statCachePool,
            asVirtualFilesystem: false
        );

        static::assertEquals(
            [
                'include' => [
                    '/my/first/path' => ['size' => 124],
                    '/my/second/path' => ['size' => 457],
                ],
                'non_include' => [
                    '/my/first/path' => ['size' => 123],
                    '/my/second/path' => ['size' => 456],
                ],
            ],
            $this->statCache->getInMemoryEntryCache()
        );
    }

    public function testGetPathStatReturnsStatWhenCachedInInMemoryCache(): void
    {
        $accessible = true;
        $this->realpathCache->allows()
            ->getCachedEventualPath('/my/path.php', true, $accessible)
            ->andReturn('/my/canonical/path.php');
        $this->statCache->setInMemoryCacheEntry('/my/canonical/path.php', isInclude: false, entry: [
            'size' => 123,
        ]);

        static::assertEquals(
            ['size' => 123],
            $this->statCache->getPathStat('/my/path.php', false)
        );
    }

    public function testGetPathStatReturnsStatWhenCachedInPsrBackingCache(): void
    {
        $accessible = true;
        $this->realpathCache->allows()
            ->getCachedEventualPath('/my/path.php', true, $accessible)
            ->andReturn('/my/canonical/path.php');
        $this->statCachePool->allows()
            ->getItem('plain_canonicalised--_my_canonical_path_php')
            ->andReturn($this->statCachePoolItem);
        $this->statCachePoolItem->allows()
            ->get()
            ->andReturn(['size' => 123]);
        $this->statCachePoolItem->allows()
            ->isHit()
            ->andReturnTrue();

        static::assertEquals(
            ['size' => 123],
            $this->statCache->getPathStat('/my/path.php', false)
        );
    }

    public function testGetPathStatReturnsNullWhenNotCachedAndNonExistent(): void
    {
        $accessible = true;
        $this->realpathCache->allows()
            ->getCachedEventualPath('/my/path.php', true, $accessible)
            ->andReturn('/my/canonical/path.php');
        $this->statCachePool->allows()
            ->getItem('plain_canonicalised--_my_canonical_path_php')
            ->andReturn($this->statCachePoolItem);
        $this->statCachePoolItem->allows()
            ->isHit()
            ->andReturnFalse();
        $this->wrappedStreamHandler->allows()
            ->urlStat('/my/path.php', 2)
            ->andReturnFalse();

        static::assertNull($this->statCache->getPathStat('/my/path.php', false));
    }

    public function testGetStreamStatReturnsStatCachedInInMemoryCache(): void
    {
        $streamWrapper = mock(StreamWrapperInterface::class);
        $streamWrapper->allows()
            ->getOpenPath()
            ->andReturn('/my/file.php');
        $streamWrapper->allows()
            ->isInclude()
            ->andReturnFalse();
        $accessible = true;
        $this->realpathCache->allows()
            ->getCachedEventualPath('/my/file.php', true, $accessible)
            ->andReturn('/my/real/file.php');
        $this->statCache->setInMemoryCacheEntry('/my/real/file.php', isInclude: false, entry: [
            'size' => 1000,
        ]);

        static::assertEquals(['size' => 1000], $this->statCache->getStreamStat($streamWrapper));
    }

    public function testGetStreamStatReturnsStatCachedInPsrBackingCache(): void
    {
        $streamWrapper = mock(StreamWrapperInterface::class);
        $streamWrapper->allows()
            ->getOpenPath()
            ->andReturn('/my/file.php');
        $streamWrapper->allows()
            ->isInclude()
            ->andReturnFalse();
        $accessible = true;
        $this->realpathCache->allows()
            ->getCachedEventualPath('/my/file.php', true, $accessible)
            ->andReturn('/my/real/file.php');
        $this->statCachePool->allows()
            ->getItem('plain_canonicalised--_my_real_file_php')
            ->andReturn($this->statCachePoolItem);
        $this->statCachePoolItem->allows()
            ->get()
            ->andReturn(['size' => 1000]);
        $this->statCachePoolItem->allows()
            ->isHit()
            ->andReturnTrue();

        static::assertEquals(['size' => 1000], $this->statCache->getStreamStat($streamWrapper));
    }

    public function testInvalidateClearsInMemoryCaches(): void
    {
        $this->statCachePool->allows('clear');
        $this->statCache->setInMemoryCacheEntry('/my/real/path', isInclude: false, entry: ['size' => 123]);
        $this->statCache->setInMemoryCacheEntry('/my/real/path', isInclude: true, entry: ['size' => 124]);

        $this->statCache->invalidate();

        $inMemoryCache = $this->statCache->getInMemoryEntryCache();
        static::assertEquals([], $inMemoryCache['non_include']);
        static::assertEquals([], $inMemoryCache['include']);
    }

    public function testInvalidateClearsPsrBackingCache(): void
    {
        $this->statCachePool->expects()
            ->clear()
            ->once();

        $this->statCache->invalidate();
    }

    public function testInvalidatePathClearsCanonicalAndEventualEntriesFromBackingCache(): void
    {
        $this->canonicaliser->expects()->canonicalise('/my/path')
            ->andReturn('/my/canonical/path');
        $this->realpathCache->expects()->getCachedEventualPath('/my/path')
            ->andReturn('/my/real/path');

        $this->statCachePool->expects()
            ->deleteItem('plain_canonicalised--_my_canonical_path')
            ->once();
        $this->statCachePool->expects()
            ->deleteItem('include_canonicalised--_my_canonical_path')
            ->once();
        $this->statCachePool->expects()
            ->deleteItem('plain_canonicalised--_my_real_path')
            ->once();
        $this->statCachePool->expects()
            ->deleteItem('include_canonicalised--_my_real_path')
            ->once();

        $this->statCache->invalidatePath('/my/path');
    }

    public function testIsDirectoryReturnsTrueWhenPathIsCachedAsADirectoryInInMemoryCache(): void
    {
        $this->statCache->setInMemoryCacheEntry('/my/canonical/dir', isInclude: false, entry: [
            'mode' => 0040755, // Directory mode.
        ]);
        $accessible = true;
        $this->realpathCache->allows()
            ->getCachedEventualPath('/my/dir', true, $accessible)
            ->andReturn('/my/canonical/dir');

        static::assertTrue($this->statCache->isDirectory('/my/dir'));
    }

    public function testIsDirectoryReturnsTrueWhenPathIsCachedAsADirectoryInPsrBackingCache(): void
    {
        $this->statCachePool->allows()
            ->getItem('plain_canonicalised--_my_canonical_dir')
            ->andReturn($this->statCachePoolItem);
        $this->statCachePoolItem->allows()
            ->get()
            // Directory mode.
            ->andReturn(['mode' => 0040755]);
        $this->statCachePoolItem->allows()
            ->isHit()
            ->andReturnTrue();
        $accessible = true;
        $this->realpathCache->allows()
            ->getCachedEventualPath('/my/dir', true, $accessible)
            ->andReturn('/my/canonical/dir');

        static::assertTrue($this->statCache->isDirectory('/my/dir'));
    }

    public function testIsDirectoryReturnsFalseWhenPathIsCachedAsAFileInInMemoryCache(): void
    {
        $this->statCache->setInMemoryCacheEntry('/my/canonical/dir', isInclude: false, entry: [
            'mode' => 0100644, // Ordinary file mode.
        ]);
        $accessible = true;
        $this->realpathCache->allows()
            ->getCachedEventualPath('/my/dir', true, $accessible)
            ->andReturn('/my/canonical/dir');

        static::assertFalse($this->statCache->isDirectory('/my/dir'));
    }

    public function testIsDirectoryReturnsFalseWhenPathIsCachedAsAFileInPsrBackingCache(): void
    {
        $this->statCachePool->allows()
            ->getItem('plain_canonicalised--_my_canonical_dir')
            ->andReturn($this->statCachePoolItem);
        $this->statCachePoolItem->allows()
            ->get()
            // Ordinary file mode.
            ->andReturn(['mode' => 0100644]);
        $this->statCachePoolItem->allows()
            ->isHit()
            ->andReturnTrue();
        $accessible = true;
        $this->realpathCache->allows()
            ->getCachedEventualPath('/my/dir', true, $accessible)
            ->andReturn('/my/canonical/dir');

        static::assertFalse($this->statCache->isDirectory('/my/dir'));
    }

    public function testIsPathCachedAsExistentReturnsTrueWhenSoInInMemoryCache(): void
    {
        $accessible = true;
        $this->realpathCache->allows()
            ->getCachedEventualPath('/my/canonical/path/to/my_module.php', true, $accessible)
            ->andReturn('/my/real/path/to/my_module.php');
        $this->statCache->setInMemoryCacheEntry('/my/real/path/to/my_module.php', isInclude: false, entry: [
            'size' => 123,
        ]);

        static::assertTrue($this->statCache->isPathCachedAsExistent('/my/canonical/path/to/my_module.php'));
    }

    public function testIsPathCachedAsExistentReturnsTrueWhenSoInPsrBackingCache(): void
    {
        $accessible = true;
        $this->realpathCache->allows()
            ->getCachedEventualPath('/my/canonical/path/to/my_module.php', true, $accessible)
            ->andReturn('/my/real/path/to/my_module.php');
        $this->statCachePool->allows()
            ->getItem('plain_canonicalised--_my_real_path_to_my_module_php')
            ->andReturn($this->statCachePoolItem);
        $this->statCachePoolItem->allows()
            ->get()
            ->andReturn(['my' => 'stat']);
        $this->statCachePoolItem->allows()
            ->isHit()
            ->andReturnTrue();

        static::assertTrue($this->statCache->isPathCachedAsExistent('/my/canonical/path/to/my_module.php'));
    }

    public function testIsPathCachedAsExistentReturnsFalseWhenSo(): void
    {
        $accessible = true;
        $this->realpathCache->allows()
            ->getCachedEventualPath('/my/canonical/path/to/my_module.php', true, $accessible)
            ->andReturn('/my/real/path/to/my_module.php');
        $this->statCachePool->allows()
            ->getItem('plain_canonicalised--_my_real_path_to_my_module_php')
            ->andReturn($this->statCachePoolItem);
        $this->statCachePoolItem->allows()
            ->isHit()
            ->andReturnFalse();

        static::assertFalse($this->statCache->isPathCachedAsExistent('/my/canonical/path/to/my_module.php'));
    }

    public function testPersistStatCacheCommitsPsrBackingCache(): void
    {
        $this->statCachePool->expects()
            ->commit()
            ->once();

        $this->statCache->persistStatCache();
    }

    public function testPopulateStatWithSizeUpdatesIncludeStatSizeInBothCachesWhenStatIsCached(): void
    {
        $cachedStat = ['size' => 1024];
        $this->statCache->setInMemoryCacheEntry('/my/real/path.php', isInclude: true, entry: $cachedStat);
        $this->statCachePoolItem->allows()
            ->get()
            ->andReturn($cachedStat);
        $this->statCachePoolItem->allows()
            ->isHit()
            ->andReturnTrue();
        $this->statCachePool->allows()
            ->getItem('include_canonicalised--_my_real_path_php')
            ->andReturn($this->statCachePoolItem);

        $this->statCachePoolItem->expects()
            ->set(['size' => 2048, 7 => 2048])
            ->once()
            ->andReturnSelf();
        $this->statCachePool->expects()
            ->saveDeferred($this->statCachePoolItem)
            ->once()
            ->andReturnTrue();

        $this->statCache->populateStatWithSize('/my/real/path.php', 2048, isInclude: true);

        $inMemoryEntryCache = $this->statCache->getInMemoryEntryCache();
        static::assertEquals(
            ['size' => 2048, 7 => 2048],
            $inMemoryEntryCache['include']['/my/real/path.php']
        );
    }

    public function testPopulateStatWithSizeUpdatesNonIncludeStatSizeInBothCachesWhenStatIsCached(): void
    {
        $cachedStat = ['size' => 1024];
        $this->statCache->setInMemoryCacheEntry('/my/real/path.php', isInclude: false, entry: $cachedStat);
        $this->statCachePoolItem->allows()
            ->get()
            ->andReturn($cachedStat);
        $this->statCachePoolItem->allows()
            ->isHit()
            ->andReturnTrue();
        $this->statCachePool->allows()
            ->getItem('plain_canonicalised--_my_real_path_php')
            ->andReturn($this->statCachePoolItem);

        $this->statCachePoolItem->expects()
            ->set(['size' => 2048, 7 => 2048])
            ->once()
            ->andReturnSelf();
        $this->statCachePool->expects()
            ->saveDeferred($this->statCachePoolItem)
            ->once()
            ->andReturnTrue();

        $this->statCache->populateStatWithSize('/my/real/path.php', 2048, isInclude: false);

        $inMemoryEntryCache = $this->statCache->getInMemoryEntryCache();
        static::assertEquals(
            ['size' => 2048, 7 => 2048],
            $inMemoryEntryCache['non_include']['/my/real/path.php']
        );
    }

    public function testPopulateStatWithSizeCopiesNonIncludeStatWhenIncludeNotCached(): void
    {
        $accessible = true;
        $this->realpathCache->allows()
            ->getCachedEventualPath('/my/real/path.php', true, $accessible)
            ->andReturn('/my/real/path.php');
        // Non-include cache entry exists but no include cache entry.
        $cachedNonIncludeStat = $this->statCache->statToSynthetic([
            'mode' => 0100777,
            'uid' => 10,
            'gid' => 20,
            'size' => 1024,
            'atime' => 1725558258,
            'mtime' => 1725558258,
            'ctime' => 1725558258,
        ]);
        $this->statCache->setInMemoryCacheEntry('/my/real/path.php', isInclude: false, entry: $cachedNonIncludeStat);
        // When we attempt to get the include stat, it is not found.
        $this->statCachePool->allows()
            ->getItem('include_canonicalised--_my_real_path_php')
            ->andReturn($this->statCachePoolItem);
        $this->statCachePoolItem->allows()
            ->isHit()
            ->andReturnFalse();

        $this->statCachePoolItem->expects()
            ->set($this->statCache->statToSynthetic([
                'mode' => 0100777,
                'uid' => 10,
                'gid' => 20,
                'size' => 2048,
                7 => 2048,
                'atime' => 1725558258,
                'mtime' => 1725558258,
                'ctime' => 1725558258,
            ]))
            ->once()
            ->andReturnSelf();
        $this->statCachePool->expects()
            ->saveDeferred($this->statCachePoolItem)
            ->once()
            ->andReturnTrue();

        $this->statCache->populateStatWithSize('/my/real/path.php', 2048, isInclude: true);

        $stat = $this->statCache->getInMemoryEntryCache()['include']['/my/real/path.php'];
        static::assertSame(2048, $stat['size']);
        static::assertSame(2048, $stat[7]);
    }

    public function testPopulateStatWithSizeThrowsExceptionWhenStatNotCachedAndNotInVirtualFilesystemMode(): void
    {
        $accessible = true;
        $this->realpathCache->allows()
            ->getCachedEventualPath('/my/real/path.php', true, $accessible)
            ->andReturn('/my/real/path.php');
        $this->statCachePool->allows()
            ->getItem('include_canonicalised--_my_real_path_php')
            ->andReturn($this->statCachePoolItem);
        $this->statCachePool->allows()
            ->getItem('plain_canonicalised--_my_real_path_php')
            ->andReturn($this->statCachePoolItem);
        $this->statCachePoolItem->allows()
            ->isHit()
            ->andReturnFalse();
        $this->wrappedStreamHandler->allows()
            ->urlStat('/my/real/path.php', 2)
            ->andReturnFalse();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot stat original realpath "/my/real/path.php"');

        $this->statCache->populateStatWithSize('/my/real/path.php', 1234, isInclude: true);
    }

    public function testPopulateStatWithSizeSynthesisesStatWhenNotCachedAndInVirtualFilesystemMode(): void
    {
        $this->statCache = new StatCache(
            $this->wrappedStreamHandler,
            $this->environment,
            $this->canonicaliser,
            $this->realpathCache,
            statPreloadCachePool: null,
            statCachePool: $this->statCachePool,
            asVirtualFilesystem: true
        );
        $accessible = true;
        $this->realpathCache->allows()
            ->getCachedEventualPath('/my/real/path.php', true, $accessible)
            ->andReturn('/my/real/path.php');
        $this->statCachePool->allows()
            ->getItem('include_canonicalised--_my_real_path_php')
            ->andReturn($this->statCachePoolItem);
        $this->statCachePool->allows()
            ->getItem('plain_canonicalised--_my_real_path_php')
            ->andReturn($this->statCachePoolItem);
        $this->statCachePoolItem->allows()
            ->isHit()
            ->andReturnFalse();
        $this->wrappedStreamHandler->allows()
            ->urlStat('/my/real/path.php', 2)
            ->andReturnFalse();

        $this->statCachePoolItem->expects()
            ->set($this->statCache->statToSynthetic([
                'mode' => 0100777,
                'uid' => 0,
                'gid' => 0,
                'size' => 1234,
                7 => 1234,
                'atime' => 1725558248,
                'mtime' => 1725558248,
                'ctime' => 1725558248,
            ]))
            ->once()
            ->andReturnSelf();
        $this->statCachePool->expects()
            ->saveDeferred($this->statCachePoolItem)
            ->once()
            ->andReturnTrue();

        $this->statCache->populateStatWithSize('/my/real/path.php', 1234, isInclude: true);
    }

    /**
     * @param string $path
     * @param bool $isInclude
     * @param StatCacheEntry $entry
     * @param MultipleStatCacheStorage $expectedCache
     * @dataProvider setInMemoryCacheEntryProvider
     */
    public function testSetInMemoryCacheEntry(
        string $path,
        bool $isInclude,
        array $entry,
        array $expectedCache
    ): void {
        $this->statCache->setInMemoryCacheEntry($path, $isInclude, $entry);

        static::assertEquals($expectedCache, $this->statCache->getInMemoryEntryCache());
    }

    /**
     * @return array<mixed>
     */
    public static function setInMemoryCacheEntryProvider(): array
    {
        return [
            'non-include path' => [
                'path' => '/my/non/include/path.php',
                'isInclude' => false,
                'entry' => ['size' => 100],
                'expectedCache' => [
                    'include' => [],
                    'non_include' => [
                        '/my/non/include/path.php' => ['size' => 100],
                    ],
                ],
            ],
            'include path' => [
                'path' => '/my/include/path.php',
                'isInclude' => true,
                'entry' => ['size' => 200],
                'expectedCache' => [
                    'include' => [
                        '/my/include/path.php' => ['size' => 200],
                    ],
                    'non_include' => [],
                ],
            ],
            'multiple entries' => [
                'path' => '/my/first/path.php',
                'isInclude' => true,
                'entry' => ['size' => 300],
                'expectedCache' => [
                    'include' => [
                        '/my/first/path.php' => ['size' => 300],
                    ],
                    'non_include' => [],
                ],
            ],
            'override existing' => [
                'path' => '/my/path.php',
                'isInclude' => false,
                'entry' => ['size' => 400],
                'expectedCache' => [
                    'include' => [],
                    'non_include' => [
                        '/my/path.php' => ['size' => 400],
                    ],
                ],
            ],
        ];
    }

    public function testStatToSyntheticReturnsCorrectSyntheticStat(): void
    {
        $syntheticStat = $this->statCache->statToSynthetic([
            'dev' => 2049,
            'ino' => 1234567,
            'mode' => 0100755,
            'nlink' => 1,
            'uid' => 1000,
            'gid' => 1000,
            'rdev' => 0,
            'size' => 1024,
            'atime' => 1633024800,
            'mtime' => 1633024801,
            'ctime' => 1633024802,
            'blksize' => 4096,
            'blocks' => 8,
        ]);

        static::assertEquals(
            [
                'dev' => 0,
                'ino' => 0,
                'mode' => 0100755,
                'nlink' => 0,
                'uid' => 1000,
                'gid' => 1000,
                'rdev' => 0,
                'size' => 1024,
                'atime' => 1633024800,
                'mtime' => 1633024801,
                'ctime' => 1633024802,
                'blksize' => -1,
                'blocks' => -1,

                // Stat values are also available under indexed keys.
                0 => 0,
                1 => 0,
                2 => 0100755,
                3 => 0,
                4 => 1000,
                5 => 1000,
                6 => 0,
                7 => 1024,
                8 => 1633024800,
                9 => 1633024801,
                10 => 1633024802,
                11 => -1,
                12 => -1,
            ],
            $syntheticStat
        );
    }

    public function testStatToSyntheticCorrectlyHandlesEmptyStat(): void
    {
        $syntheticStat = $this->statCache->statToSynthetic([
            'dev' => 2049,
            'ino' => 1234567,
            'mode' => 0100755,
            'nlink' => 1,
            'uid' => 1000,
            'gid' => 1000,
            'rdev' => 0,
            'size' => 1024,
            'atime' => 1633024800,
            'mtime' => 1633024801,
            'ctime' => 1633024802,
            'blksize' => 4096,
            'blocks' => 8,
        ]);

        static::assertEquals(
            [
                'dev' => 0,
                'ino' => 0,
                'mode' => 0100755,
                'nlink' => 0,
                'uid' => 1000,
                'gid' => 1000,
                'rdev' => 0,
                'size' => 1024,
                'atime' => 1633024800,
                'mtime' => 1633024801,
                'ctime' => 1633024802,
                'blksize' => -1,
                'blocks' => -1,

                // Stat values are also available under indexed keys.
                0 => 0,
                1 => 0,
                2 => 0100755,
                3 => 0,
                4 => 1000,
                5 => 1000,
                6 => 0,
                7 => 1024,
                8 => 1633024800,
                9 => 1633024801,
                10 => 1633024802,
                11 => -1,
                12 => -1,
            ],
            $syntheticStat
        );
    }

    public function testSynthesiseStatSuccessfullySynthesisesStat(): void
    {
        $this->statCachePool->allows()
            ->getItem('plain_canonicalised--_path_to_file')
            ->andReturn($this->statCachePoolItem);
        $this->statCachePoolItem->allows()
            ->isHit()
            ->andReturnFalse();
        $this->statCache = new StatCache(
            $this->wrappedStreamHandler,
            $this->environment,
            $this->canonicaliser,
            $this->realpathCache,
            null,
            $this->statCachePool,
            asVirtualFilesystem: true
        );

        // Expect the cache to be set with the generated synthetic stat
        $this->statCachePoolItem->expects()
            ->set(Mockery::subset([
                'mode' => (0100000 | 0777), // Regular file with mode 0777.
                'size' => 100,
                'atime' => 1725558258 - 10,
                'mtime' => 1725558258 - 10,
                'ctime' => 1725558258 - 10,
            ]))
            ->once();
        $this->statCachePool->expects()
            ->saveDeferred($this->statCachePoolItem)
            ->once();

        $this->statCache->synthesiseStat('/path/to/file', isInclude: false, isDir: false, mode: 0777, size: 100);
    }

    public function testSynthesiseStatThrowsExceptionWhenNotVirtualFilesystem(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot use fully synthetic stats outside of a virtual filesystem');

        $this->statCache->synthesiseStat('/path/to/file', isInclude: false, isDir: false, mode: 0777, size: 100);
    }

    public function testSynthesiseStatThrowsExceptionIfSyntheticStatAlreadyExists(): void
    {
        $this->statCachePoolItem->allows()
            ->isHit()
            ->andReturnTrue();
        $this->statCachePool->allows()
            ->getItem('plain_canonicalised--_path_to_file')
            ->andReturn($this->statCachePoolItem);
        $this->statCache = new StatCache(
            $this->wrappedStreamHandler,
            $this->environment,
            $this->canonicaliser,
            $this->realpathCache,
            null,
            $this->statCachePool,
            asVirtualFilesystem: true
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Synthetic stat already exists for path "/path/to/file"');

        $this->statCache->synthesiseStat('/path/to/file', isInclude: false, isDir: false, mode: 0777, size: 100);
    }

    public function testUpdateSyntheticStatUpdatesFieldsCorrectly(): void
    {
        $this->statCachePool->allows()
            ->getItem('plain_canonicalised--_path_to_file')
            ->andReturn($this->statCachePoolItem);
        $this->statCache = new StatCache(
            $this->wrappedStreamHandler,
            $this->environment,
            $this->canonicaliser,
            $this->realpathCache,
            null,
            $this->statCachePool,
            asVirtualFilesystem: true
        );
        $this->statCache->setInMemoryCacheEntry('/path/to/file', isInclude: false, entry: [
            'dev' => 0,
            'ino' => 0,
            'mode' => 0100644, // Regular file, rw-r--r--.
            'nlink' => 0,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => 1234,
            'atime' => 1609459200, // Jan 1, 2021.
            'mtime' => 1609459200,
            'ctime' => 1609459200,
            'blksize' => -1,
            'blocks' => -1,

            0 => 0,
            1 => 0,
            2 => 0100644, // Regular file, rw-r--r--.
            3 => 0,
            4 => 0,
            5 => 0,
            6 => 0,
            7 => 1234,
            8 => 1609459200, // Jan 1, 2021.
            9 => 1609459200,
            10 => 1609459200,
            11 => -1,
            12 => -1,
        ]);
        $newSize = 5678;
        $newMode = 0755; // Update permissions to rwxr-xr-x.
        $newMtime = 1609462800; // Update modification time to 1 hour later.
        $expectedNewStat = [
            'dev' => 0,
            'ino' => 0,
            'mode' => 0100755,
            'nlink' => 0,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => 5678,
            'atime' => 1609459200,
            'mtime' => 1609462800,
            'ctime' => 1609459200,
            'blksize' => -1,
            'blocks' => -1,

            // Stat values are also available under indexed keys.
            0 => 0,
            1 => 0,
            2 => 0100755,
            3 => 0,
            4 => 0,
            5 => 0,
            6 => 0,
            7 => 5678,
            8 => 1609459200,
            9 => 1609462800,
            10 => 1609459200,
            11 => -1,
            12 => -1,
        ];

        $this->statCachePoolItem->expects()
            ->set($expectedNewStat)
            ->once();
        $this->statCachePool->expects()
            ->saveDeferred($this->statCachePoolItem)
            ->once();

        $this->statCache->updateSyntheticStat(
            '/path/to/file',
            isInclude: false,
            mode: $newMode,
            size: $newSize,
            modificationTime: $newMtime
        );

        static::assertEquals(
            $expectedNewStat,
            $this->statCache->getInMemoryEntryCache()['non_include']['/path/to/file']
        );
    }

    public function testUpdateSyntheticStatThrowsExceptionIfNoSyntheticStatExists(): void
    {
        $this->statCachePool->allows()
            ->getItem('plain_canonicalised--_path_to_nonexistent_file')
            ->andReturn($this->statCachePoolItem);
        $this->statCachePoolItem->allows()
            ->isHit()
            ->andReturnFalse();
        $this->statCache = new StatCache(
            $this->wrappedStreamHandler,
            $this->environment,
            $this->canonicaliser,
            $this->realpathCache,
            null,
            $this->statCachePool,
            asVirtualFilesystem: true
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No synthetic stat exists for path "/path/to/nonexistent/file"');

        $this->statCache->updateSyntheticStat(
            '/path/to/nonexistent/file',
            isInclude: false,
            mode: 0755,
            size: 5678
        );
    }
}
