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

namespace Nytris\Boost\Tests\Unit;

use Mockery\MockInterface;
use Nytris\Boost\Boost;
use Nytris\Boost\FsCache\FsCacheInterface;
use Nytris\Boost\Tests\AbstractTestCase;

/**
 * Class BoostTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class BoostTest extends AbstractTestCase
{
    private Boost $boost;
    private MockInterface&FsCacheInterface $fsCache;

    public function setUp(): void
    {
        $this->fsCache = mock(FsCacheInterface::class);

        $this->boost = new Boost(fsCache: $this->fsCache);
    }

    public function testGetRealpathResolvesViaFsCache(): void
    {
        $this->fsCache->allows()
            ->getRealpath('/my/path')
            ->andReturn('/my/realpath');

        static::assertSame('/my/realpath', $this->boost->getRealpath('/my/path'));
    }
}
