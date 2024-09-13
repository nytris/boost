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
use Nytris\Boost\FsCache\CanonicaliserInterface;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\Stream\Handler\FsCachingStreamHandlerInterface;
use Nytris\Boost\FsCache\Stream\Opener\StreamOpener;
use Nytris\Boost\Tests\AbstractTestCase;

/**
 * Class StreamOpenerTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class StreamOpenerTest extends AbstractTestCase
{
    private MockInterface&CanonicaliserInterface $canonicaliser;
    private MockInterface&ContentsCacheInterface $contentsCache;
    private MockInterface&FsCachingStreamHandlerInterface $streamHandler;
    private StreamOpener $streamOpener;
    private MockInterface&StreamWrapperInterface $streamWrapper;
    private MockInterface&StreamHandlerInterface $wrappedStreamHandler;

    public function setUp(): void
    {
        $this->canonicaliser = mock(CanonicaliserInterface::class);
        $this->contentsCache = mock(ContentsCacheInterface::class);
        $this->streamHandler = mock(FsCachingStreamHandlerInterface::class);
        $this->streamWrapper = mock(StreamWrapperInterface::class);
        $this->wrappedStreamHandler = mock(StreamHandlerInterface::class);

        $this->streamOpener = new StreamOpener(
            $this->wrappedStreamHandler,
            $this->canonicaliser,
            $this->contentsCache
        );
    }

    public function testOpenStreamInvalidatesCanonicalPathForWriteModes(): void
    {
        $this->canonicaliser->allows()
            ->canonicalise('/my/uncanonical/path/to/my_file.txt')
            ->andReturn('/my/canonical/path/to/my_file.txt');
        $openedPath = null;
        $this->wrappedStreamHandler->allows()
            ->streamOpen(
                $this->streamWrapper,
                '/my/canonical/path/to/my_file.txt',
                'r+',
                0,
                $openedPath
            )
            ->andReturn(['resource' => 21, 'isInclude' => false]);

        $this->streamHandler->expects()
            ->invalidatePath('/my/canonical/path/to/my_file.txt')
            ->once();

        $this->streamOpener->openStream(
            $this->streamWrapper,
            '/my/uncanonical/path/to/my_file.txt',
            'r+',
            0,
            $openedPath,
            $this->streamHandler
        );
    }
}
