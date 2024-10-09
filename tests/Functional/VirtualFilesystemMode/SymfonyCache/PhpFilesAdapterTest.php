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

namespace Nytris\Boost\Tests\Functional\VirtualFilesystemMode\SymfonyCache;

use Asmblah\PhpCodeShift\Shifter\Filter\FileFilter;
use Nytris\Boost\Boost;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\Contents\SinglePoolContentsCache;
use Nytris\Boost\Tests\Functional\AbstractFunctionalTestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

/**
 * Class PhpFilesAdapterTest.
 *
 * Tests the virtual filesystem in conjunction with Symfony Cache's PhpFilesAdapter and OPcache.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class PhpFilesAdapterTest extends AbstractFunctionalTestCase
{
    private PhpFilesAdapter $adapter;
    private Boost $boost;
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

        $this->boost->install();

        $this->adapter = new PhpFilesAdapter(directory: '/my/virtual/var/cache');
    }

    public function tearDown(): void
    {
        $this->boost->uninstall();
    }

    public function testItemsMayBeSetAndFetched(): void
    {
        mkdir('/my/virtual/var/cache');
        /** @var CacheItemInterface $item */
        $item = $this->adapter->getItem('my_key');
        $item->set('my value');
        $this->adapter->save($item);

        /** @var CacheItemInterface $item */
        $item = $this->adapter->getItem('my_key');

        static::assertTrue($item->isHit());
        static::assertSame('my value', $item->get());
    }

    public function testItemsMayBeChanged(): void
    {
        mkdir('/my/virtual/var/cache');
        /** @var CacheItemInterface $item */
        $item = $this->adapter->getItem('my_key');
        $item->set('my first value');
        $this->adapter->save($item);
        $this->adapter->commit();

        /** @var CacheItemInterface $item */
        $item = $this->adapter->getItem('my_key');
        $item->set('my second value');
        $this->adapter->save($item);
        $this->adapter->commit();
        /** @var CacheItemInterface $item */
        $item = $this->adapter->getItem('my_key');

        static::assertTrue($item->isHit());
        static::assertSame('my second value', $item->get());
    }
}
