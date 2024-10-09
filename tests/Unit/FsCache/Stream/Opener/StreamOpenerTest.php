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

namespace Nytris\Boost\Tests\Unit\FsCache\Stream\Opener;

use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapperInterface;
use Mockery\MockInterface;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\Realpath\RealpathCacheInterface;
use Nytris\Boost\FsCache\Stat\StatCacheInterface;
use Nytris\Boost\FsCache\Stream\Opener\StreamOpener;
use Nytris\Boost\Tests\AbstractTestCase;

/**
 * Class StreamOpenerTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class StreamOpenerTest extends AbstractTestCase
{
    private MockInterface&ContentsCacheInterface $contentsCache;
    private MockInterface&RealpathCacheInterface $realpathCache;
    private MockInterface&StatCacheInterface $statCache;
    private StreamOpener $streamOpener;
    private MockInterface&StreamWrapperInterface $streamWrapper;
    private MockInterface&StreamHandlerInterface $wrappedStreamHandler;

    public function setUp(): void
    {
        $this->contentsCache = mock(ContentsCacheInterface::class);
        $this->realpathCache = mock(RealpathCacheInterface::class);
        $this->statCache = mock(StatCacheInterface::class);
        $this->streamWrapper = mock(StreamWrapperInterface::class);
        $this->wrappedStreamHandler = mock(StreamHandlerInterface::class);

        $this->streamOpener = new StreamOpener(
            $this->wrappedStreamHandler,
            $this->realpathCache,
            $this->statCache,
            $this->contentsCache,
            asVirtualFilesystem: false
        );
    }

    public function testOpenStreamInvalidatesPathForWriteModesInCachingMode(): void
    {
        $openedPath = null;
        $this->wrappedStreamHandler->allows()
            ->streamOpen(
                $this->streamWrapper,
                '/my/path/to/my_file.txt',
                'r+',
                0,
                $openedPath
            )
            ->andReturn(['resource' => 21, 'isInclude' => false]);

        $this->realpathCache->expects()
            ->invalidatePath('/my/path/to/my_file.txt')
            ->once();
        $this->statCache->expects()
            ->invalidatePath('/my/path/to/my_file.txt')
            ->once();
        $this->contentsCache->expects()
            ->invalidatePath('/my/path/to/my_file.txt')
            ->once();

        $this->streamOpener->openStream(
            $this->streamWrapper,
            '/my/path/to/my_file.txt',
            'r+',
            0,
            $openedPath
        );
    }
}
