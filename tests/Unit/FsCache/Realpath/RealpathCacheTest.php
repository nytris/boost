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

namespace Nytris\Boost\Tests\Unit\FsCache\Realpath;

use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Mockery\MockInterface;
use Nytris\Boost\FsCache\CanonicaliserInterface;
use Nytris\Boost\FsCache\Realpath\RealpathCache;
use Nytris\Boost\FsCache\Realpath\RealpathCacheInterface;
use Nytris\Boost\Tests\AbstractTestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Class RealpathCacheTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class RealpathCacheTest extends AbstractTestCase
{
    private MockInterface&CanonicaliserInterface $canonicaliser;
    private RealpathCache $realpathCache;
    private MockInterface&CacheItemPoolInterface $realpathCachePool;
    private MockInterface&CacheItemInterface $realpathCachePoolItem;
    private MockInterface&StreamHandlerInterface $wrappedStreamHandler;

    public function setUp(): void
    {
        $this->canonicaliser = mock(CanonicaliserInterface::class);
        $this->realpathCachePool = mock(CacheItemPoolInterface::class);
        $this->realpathCachePoolItem = mock(CacheItemInterface::class);
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

        $this->realpathCache = new RealpathCache(
            $this->wrappedStreamHandler,
            $this->canonicaliser,
            realpathPreloadCachePool: null,
            realpathCachePool: $this->realpathCachePool,
            cacheNonExistentFiles: true,
            asVirtualFilesystem: false
        );
    }

    public function testConstructorLoadsInMemoryCacheFromPreloadPoolWhenProvided(): void
    {
        $preloadCachePool = mock(CacheItemPoolInterface::class);
        $preloadItem = mock(CacheItemInterface::class, [
            'get' => [
                '/my/first/path' => ['realpath' => '/my/first/realpath'],
                '/my/second/path' => ['realpath' => '/my/second/realpath'],
            ],
            'isHit' => true,
        ]);

        $preloadCachePool->expects()
            ->getItem(RealpathCacheInterface::PRELOAD_CACHE_KEY)
            ->andReturn($preloadItem)
            ->once();

        $this->realpathCache = new RealpathCache(
            $this->wrappedStreamHandler,
            $this->canonicaliser,
            realpathPreloadCachePool: $preloadCachePool,
            realpathCachePool: $this->realpathCachePool,
            cacheNonExistentFiles: true,
            asVirtualFilesystem: false
        );

        static::assertEquals(
            [
                '/my/first/path' => ['realpath' => '/my/first/realpath'],
                '/my/second/path' => ['realpath' => '/my/second/realpath'],
            ],
            $this->realpathCache->getInMemoryEntryCache()
        );
    }

    public function testCacheRealpathCanCacheARealpath(): void
    {
        $this->realpathCache = new RealpathCache(
            $this->wrappedStreamHandler,
            $this->canonicaliser,
            realpathPreloadCachePool: null,
            realpathCachePool: new ArrayAdapter(),
            cacheNonExistentFiles: true,
            asVirtualFilesystem: false
        );

        $this->realpathCache->cacheRealpath(
            canonicalPath: '/my/custom/canonical/path',
            realpath: '/my/custom/real/path'
        );

        static::assertSame(
            '/my/custom/real/path',
            $this->realpathCache->getRealpath('/my/custom/canonical/path')
        );
        static::assertSame(
            '/my/custom/real/path',
            $this->realpathCache->getRealpath('/my/custom/real/path')
        );
        static::assertEquals(
            [
                'realpath' => '/my/custom/real/path',
            ],
            $this->realpathCache->getRealpathCacheEntry(
                '/my/custom/canonical/path',
                followSymlinks: true
            )
        );
    }

    public function testDeleteBackingCacheEntryDeletesFromInMemoryCache(): void
    {
        $this->realpathCachePool->allows()
            ->deleteItem('canonicalised--_my_real_path')
            ->andReturnTrue();

        $this->realpathCache->deleteBackingCacheEntry('/my/real/path');

        static::assertEquals(
            [],
            $this->realpathCache->getInMemoryEntryCache()
        );
    }

    public function testDeleteBackingCacheEntryDeletesFromPsrBackingCache(): void
    {
        $this->realpathCachePool->expects()
            ->deleteItem('canonicalised--_my_real_path')
            ->once()
            ->andReturnTrue();

        $this->realpathCache->deleteBackingCacheEntry('/my/real/path');
    }

    public function testGetBackingCacheEntryPrefersInMemoryCache(): void
    {
        $this->realpathCache->setInMemoryCacheEntry('/my/real/path', ['my' => 'entry']);

        $this->realpathCachePool->expects('getItem')
            ->never();

        static::assertEquals(
            ['my' => 'entry'],
            $this->realpathCache->getBackingCacheEntry('/my/real/path')
        );
    }

    public function testGetBackingCacheEntryFetchesFromPsrBackingCacheWhenNotInMemoryCache(): void
    {
        $this->realpathCachePool->allows()
            ->getItem('canonicalised--_my_real_path')
            ->andReturn($this->realpathCachePoolItem);
        $this->realpathCachePoolItem->allows()
            ->get()
            ->andReturn(['my' => 'entry']);
        $this->realpathCachePoolItem->allows()
            ->isHit()
            ->andReturnTrue();

        static::assertEquals(
            ['my' => 'entry'],
            $this->realpathCache->getBackingCacheEntry('/my/real/path')
        );
    }

    public function testGetCachedEventualPathFetchesPresentPathWithoutHittingPsrBackingCache(): void
    {
        $accessible = false;
        $this->realpathCache->setInMemoryCacheEntry(
            '/my/canonical/../path',
            ['canonical' => '/my/canonical/path']
        );
        $this->realpathCache->setInMemoryCacheEntry(
            '/my/canonical/path',
            ['realpath' => '/my/real/path']
        );

        static::assertSame(
            '/my/real/path',
            $this->realpathCache->getCachedEventualPath(
                '/my/canonical/../path',
                accessible: $accessible
            )
        );
        static::assertTrue($accessible);
    }

    public function testGetCachedEventualPathMarksAccessibleFalseWhenCachedAsNonExistent(): void
    {
        $accessible = false;
        $this->realpathCache->setInMemoryCacheEntry(
            '/my/canonical/../path',
            ['canonical' => '/my/canonical/path']
        );
        $this->realpathCache->setInMemoryCacheEntry(
            '/my/canonical/path',
            ['realpath' => '/my/real/path', 'exists' => false]
        );

        static::assertSame(
            '/my/real/path',
            $this->realpathCache->getCachedEventualPath(
                '/my/canonical/../path',
                accessible: $accessible
            )
        );
        static::assertFalse($accessible);
    }

    public function testGetInMemoryEntryCacheReturnsEmptyArrayInitially(): void
    {
        static::assertEquals([], $this->realpathCache->getInMemoryEntryCache());
    }

    public function testGetRealpathReturnsRealpathForExistingFile(): void
    {
        $this->realpathCache->setInMemoryCacheEntry('/my/test/path', [
            'realpath' => '/my/real/path',
        ]);

        static::assertEquals(
            '/my/real/path',
            $this->realpathCache->getRealpath('/my/test/path')
        );
    }

    public function testGetRealpathCachesNonExistentFileWhenEnabled(): void
    {
        $this->realpathCache = new RealpathCache(
            $this->wrappedStreamHandler,
            $this->canonicaliser,
            realpathPreloadCachePool: null,
            realpathCachePool: new ArrayAdapter(),
            cacheNonExistentFiles: true,
            asVirtualFilesystem: false
        );

        $path = '/my/non/existent/path';

        static::assertNull($this->realpathCache->getRealpath($path));
        static::assertEquals(
            ['exists' => false],
            $this->realpathCache->getRealpathCacheEntry($path, followSymlinks: true)
        );
    }

    public function testGetRealpathDoesNotCacheNonExistentFileWhenDisabled(): void
    {
        $path = '/my/non/existent/path';
        $this->realpathCache = new RealpathCache(
            $this->wrappedStreamHandler,
            $this->canonicaliser,
            realpathPreloadCachePool: null,
            realpathCachePool: new ArrayAdapter(),
            cacheNonExistentFiles: false, // Disable caching of non-existent files.
            asVirtualFilesystem: false
        );

        static::assertNull($this->realpathCache->getRealpath($path));
        static::assertNull($this->realpathCache->getRealpathCacheEntry($path, followSymlinks: true));
    }

    public function testGetRealpathCacheEntryReturnsCorrectEntryForDirectAccessWhenCached(): void
    {
        $this->realpathCache->setInMemoryCacheEntry('/my/test/path', ['realpath' => '/my/real/path']);

        static::assertEquals(
            ['realpath' => '/my/real/path'],
            $this->realpathCache->getRealpathCacheEntry('/my/test/path', followSymlinks: true)
        );
    }

    public function testGetRealpathCacheEntryReturnsCorrectEntryUsingCanonicalPathWhenCached(): void
    {
        $this->realpathCache->setInMemoryCacheEntry('/my/test/path', ['canonical' => '/my/canonical/path']);
        $this->realpathCache->setInMemoryCacheEntry('/my/canonical/path', ['realpath' => '/my/real/path']);

        static::assertEquals(
            ['realpath' => '/my/real/path'],
            $this->realpathCache->getRealpathCacheEntry('/my/test/path', followSymlinks: true)
        );
    }

    public function testGetRealpathCacheEntryReturnsCorrectEntryUsingCanonicalAndSymlinkPathsWhenCachedAndEnabled(): void
    {
        $this->realpathCache->setInMemoryCacheEntry('/my/test/path', ['canonical' => '/my/canonical/path']);
        $this->realpathCache->setInMemoryCacheEntry('/my/canonical/path', ['symlink' => '/my/symlink/path']);
        $this->realpathCache->setInMemoryCacheEntry('/my/symlink/path', ['realpath' => '/my/real/path']);

        static::assertEquals(
            ['realpath' => '/my/real/path'],
            $this->realpathCache->getRealpathCacheEntry('/my/test/path', followSymlinks: true)
        );
    }

    public function testGetRealpathCacheEntryReturnsCorrectEntryUsingCanonicalAndSymlinkPathsWhenCachedButDisabled(): void
    {
        $this->realpathCache->setInMemoryCacheEntry('/my/test/path', ['canonical' => '/my/canonical/path']);
        $this->realpathCache->setInMemoryCacheEntry('/my/canonical/path', ['symlink' => '/my/symlink/path']);
        $this->realpathCache->setInMemoryCacheEntry('/my/symlink/path', ['realpath' => '/my/real/path']);

        static::assertEquals(
            // Note that symlink cache entry is not followed through to and fetched when flag is off.
            ['symlink' => '/my/symlink/path'],
            $this->realpathCache->getRealpathCacheEntry('/my/test/path', followSymlinks: false)
        );
    }

    public function testGetRealpathCacheEntryReturnsNullWhenNotCached(): void
    {
        $this->realpathCachePool->allows()
            ->getItem('canonicalised--_my_unknown_path')
            ->andReturn($this->realpathCachePoolItem);
        $this->realpathCachePoolItem->allows()
            ->isHit()
            ->andReturnFalse();

        static::assertNull($this->realpathCache->getRealpathCacheEntry('/my/unknown/path', followSymlinks: true));
    }

    public function testGetRealpathCacheEntryReturnsNullWhenOnlyCanonicalisationIsCached(): void
    {
        $this->realpathCache->setInMemoryCacheEntry('/my/unknown/path', [
            'canonical' => '/my/canonical/unknown/path',
        ]);
        $this->realpathCachePool->allows()
            ->getItem('canonicalised--_my_canonical_unknown_path')
            ->andReturn($this->realpathCachePoolItem);
        $this->realpathCachePoolItem->allows()
            ->isHit()
            ->andReturnFalse();

        static::assertNull($this->realpathCache->getRealpathCacheEntry('/my/unknown/path', followSymlinks: true));
    }

    public function testInvalidateClearsInMemoryCache(): void
    {
        $this->realpathCachePool->allows('clear');
        $this->realpathCache->setInMemoryCacheEntry('/my/test/path', ['realpath' => '/my/real/path']);

        $this->realpathCache->invalidate();

        static::assertEquals([], $this->realpathCache->getInMemoryEntryCache());
    }

    public function testInvalidateClearsPsrBackingCache(): void
    {
        $this->realpathCache->setInMemoryCacheEntry('/my/test/path', ['realpath' => '/my/real/path']);

        $this->realpathCachePool->expects()
            ->clear()
            ->once();

        $this->realpathCache->invalidate();
    }

    public function testInvalidatePathClearsCanonicalAndEventualEntriesFromInMemoryCache(): void
    {
        $this->canonicaliser->allows()
            ->canonicalise('/my/first/path')
            ->andReturn('/my/canonical/first/path');
        $this->canonicaliser->allows()
            ->canonicalise('/my/second/path')
            ->andReturn('/my/canonical/second/path');
        $this->realpathCachePool->allows('deleteItem');
        $this->realpathCache->setInMemoryCacheEntry('/my/first/path', [
            'canonical' => '/my/canonical/first/path',
        ]);
        $this->realpathCache->setInMemoryCacheEntry('/my/canonical/first/path', [
            'realpath' => '/my/real/first/path',
        ]);
        $this->realpathCache->setInMemoryCacheEntry('/my/second/path', [
            'canonical' => '/my/canonical/second/path',
        ]);
        $this->realpathCache->setInMemoryCacheEntry('/my/canonical/second/path', [
            'realpath' => '/my/real/second/path',
        ]);

        $this->realpathCache->invalidatePath('/my/second/path');

        static::assertEquals(
            [
                '/my/first/path' => ['canonical' => '/my/canonical/first/path'],
                '/my/canonical/first/path' => ['realpath' => '/my/real/first/path'],
                // Note that canonical entries are left in place.
                // This is because there are an infinite number of paths that canonicalise
                // to a particular one, so it is more efficient to simply remove their target entry.
                '/my/second/path' => ['canonical' => '/my/canonical/second/path'],
            ],
            $this->realpathCache->getInMemoryEntryCache()
        );
    }

    public function testInvalidatePathClearsCanonicalAndEventualEntriesFromPsrBackingCache(): void
    {
        $this->realpathCache->setInMemoryCacheEntry('/my/first/path', ['realpath' => '/my/real/first/path']);
        $this->realpathCache->setInMemoryCacheEntry('/my/second/path', ['realpath' => '/my/real/second/path']);

        $this->realpathCachePool->expects()
            ->deleteItem('canonicalised--_my_second_path')
            ->once();
        $this->realpathCachePool->expects()
            ->deleteItem('canonicalised--_my_real_second_path')
            ->once();

        $this->realpathCache->invalidatePath('/my/second/path');
    }

    public function testPersistRealpathCachePersistsBackingStore(): void
    {
        $this->realpathCachePool->expects()
            ->commit()
            ->once();

        $this->realpathCache->persistRealpathCache();
    }

    public function testSetBackingCacheEntryStoresInMemoryCache(): void
    {
        $item = mock(CacheItemInterface::class, [
            'isHit' => false,
            'set' => null,
        ]);
        $this->realpathCachePool->allows()
            ->getItem('canonicalised--_my_real_path')
            ->andReturn($item);
        $this->realpathCachePool->allows('saveDeferred');

        $this->realpathCache->setBackingCacheEntry('/my/real/path', ['my' => 'entry']);

        static::assertEquals(
            ['my' => 'entry'],
            $this->realpathCache->getBackingCacheEntry('/my/real/path')
        );
        static::assertEquals(
            [
                '/my/real/path' => ['my' => 'entry'],
            ],
            $this->realpathCache->getInMemoryEntryCache()
        );
    }

    public function testSetInMemoryCacheEntryStoresEntry(): void
    {
        $this->realpathCache->setInMemoryCacheEntry('/my/test/path', ['realpath' => '/my/real/path']);

        static::assertEquals(
            ['realpath' => '/my/real/path'],
            $this->realpathCache->getBackingCacheEntry('/my/test/path')
        );
    }
}
