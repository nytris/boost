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
        $this->realpathCachePoolItem = mock(CacheItemInterface::class, [
            'get' => [
                '/my/resolved/../path/to/my_module.php' => [
                    'canonical' => '/my/path/to/my_module.php',
                ],
            ],
            'isHit' => true,
        ]);
        $this->wrappedStreamHandler = mock(StreamHandlerInterface::class);

        $this->canonicaliser->allows('canonicalise')
            ->andReturnArg(0)
            ->byDefault();
        $this->canonicaliser->allows('canonicaliseCacheKey')
            ->andReturnUsing(fn (string $key) => 'canonicalised--' . preg_replace('#\W#', '_', $key))
            ->byDefault();

        $this->realpathCachePool->allows()
            ->getItem('my_realpath_cache_key')
            ->andReturn($this->realpathCachePoolItem)
            ->byDefault();

        $this->wrappedStreamHandler->allows('unwrapped')
            ->andReturnUsing(fn (callable $callback) => $callback())
            ->byDefault();

        $this->realpathCache = new RealpathCache(
            $this->wrappedStreamHandler,
            $this->canonicaliser,
            $this->realpathCachePool,
            cacheNonExistentFiles: true,
            asVirtualFilesystem: false
        );
    }

    public function testCacheRealpathCanCacheARealpath(): void
    {
        $this->realpathCache = new RealpathCache(
            $this->wrappedStreamHandler,
            $this->canonicaliser,
            new ArrayAdapter(),
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

    public function testGetRealpathCachesNonExistentFileWhenEnabled(): void
    {
        $this->realpathCache = new RealpathCache(
            $this->wrappedStreamHandler,
            $this->canonicaliser,
            new ArrayAdapter(),
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
            new ArrayAdapter(),
            cacheNonExistentFiles: false, // Disable caching of non-existent files.
            asVirtualFilesystem: false
        );

        static::assertNull($this->realpathCache->getRealpath($path));
        static::assertNull($this->realpathCache->getRealpathCacheEntry($path, followSymlinks: true));
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
    }
}
