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

use Nytris\Boost\Boost;
use Nytris\Boost\Tests\Functional\AbstractFunctionalTestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

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
    protected CacheItemPoolInterface $realpathCachePool;
    protected CacheItemPoolInterface $statCachePool;
    protected string $varPath;

    public function setUp(): void
    {
        parent::setUp();

        $this->realpathCachePool = new ArrayAdapter();
        $this->statCachePool = new ArrayAdapter();

        $this->varPath = dirname(__DIR__, 4) . '/var/test';
        @mkdir($this->varPath, recursive: true);

        $this->boost = new Boost(
            realpathCachePool: $this->realpathCachePool,
            statCachePool: $this->statCachePool
        );
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->boost->uninstall();

        $this->rimrafDescendantsOf($this->varPath);
    }
}
