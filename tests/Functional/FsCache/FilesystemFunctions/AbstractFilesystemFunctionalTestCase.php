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

namespace Nytris\Boost\Tests\Functional\FsCache\FilesystemFunctions;

use Mockery\MockInterface;
use Nytris\Boost\Boost;
use Nytris\Boost\Tests\Functional\AbstractFunctionalTestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class AbstractFilesystemFunctionalTestCase.
 *
 * Base class for all functional filesystem test cases.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
abstract class AbstractFilesystemFunctionalTestCase extends AbstractFunctionalTestCase
{
    protected Boost $boost;
    protected MockInterface&CacheItemInterface $realpathCacheItem;
    protected MockInterface&CacheItemPoolInterface $realpathCachePool;
    private MockInterface&CacheItemInterface $statCacheItemForIncludes;
    private MockInterface&CacheItemInterface $statCacheItemForNonIncludes;
    protected MockInterface&CacheItemPoolInterface $statCachePool;
    protected string $varPath;

    public function setUp(): void
    {
        parent::setUp();

        $this->realpathCachePool = mock(CacheItemPoolInterface::class, [
            'saveDeferred' => null,
        ]);
        $this->statCachePool = mock(CacheItemPoolInterface::class, [
            'saveDeferred' => null,
        ]);
        $this->realpathCacheItem = mock(CacheItemInterface::class, [
            'get' => [],
            'isHit' => true,
            'set' => null,
        ]);
        $this->statCacheItemForIncludes = mock(CacheItemInterface::class, [
            'get' => [],
            'isHit' => true,
            'set' => null,
        ]);
        $this->statCacheItemForNonIncludes = mock(CacheItemInterface::class, [
            'get' => [],
            'isHit' => true,
            'set' => null,
        ]);

        $this->varPath = dirname(__DIR__, 4) . '/var/test';
        @mkdir($this->varPath, recursive: true);

        $this->boost = new Boost(
            realpathCachePool: $this->realpathCachePool,
            statCachePool: $this->statCachePool,
            realpathCacheKey: '__my_realpath_cache',
            statCacheKey: '__my_stat_cache'
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
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->boost->uninstall();

        $this->rimrafDescendantsOf($this->varPath);
    }
}
