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

namespace Nytris\Boost\Tests\Unit\FsCache\Stream\Handler;

use Asmblah\PhpCodeShift\Shifter\Filter\FileFilter;
use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapperInterface;
use Mockery\MockInterface;
use Nytris\Boost\Environment\EnvironmentInterface;
use Nytris\Boost\FsCache\CanonicaliserInterface;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\Realpath\RealpathCacheInterface;
use Nytris\Boost\FsCache\Stat\StatCacheInterface;
use Nytris\Boost\FsCache\Stream\Handler\FsCachingStreamHandler;
use Nytris\Boost\FsCache\Stream\Opener\StreamOpenerInterface;
use Nytris\Boost\Tests\AbstractTestCase;

/**
 * Class FsCachingStreamHandlerTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class FsCachingStreamHandlerTest extends AbstractTestCase
{
    private MockInterface&CanonicaliserInterface $canonicaliser;
    private MockInterface&ContentsCacheInterface $contentsCache;
    private MockInterface&EnvironmentInterface $environment;
    private FsCachingStreamHandler $fsCachingStreamHandler;
    private MockInterface&RealpathCacheInterface $realpathCache;
    private MockInterface&StatCacheInterface $statCache;
    private MockInterface&StreamOpenerInterface $streamOpener;
    private MockInterface&StreamWrapperInterface $streamWrapper;
    private MockInterface&StreamHandlerInterface $wrappedStreamHandler;

    public function setUp(): void
    {
        $this->canonicaliser = mock(CanonicaliserInterface::class);
        $this->contentsCache = mock(ContentsCacheInterface::class);
        $this->environment = mock(EnvironmentInterface::class);
        $this->realpathCache = mock(RealpathCacheInterface::class);
        $this->statCache = mock(StatCacheInterface::class);
        $this->streamOpener = mock(StreamOpenerInterface::class);
        $this->streamWrapper = mock(StreamWrapperInterface::class);
        $this->wrappedStreamHandler = mock(StreamHandlerInterface::class);

        $this->canonicaliser->allows('canonicalise')
            ->andReturnArg(0)
            ->byDefault();

        $this->wrappedStreamHandler->allows('unwrapped')
            ->andReturnUsing(fn (callable $callback) => $callback())
            ->byDefault();

        $this->fsCachingStreamHandler = new FsCachingStreamHandler(
            $this->wrappedStreamHandler,
            $this->environment,
            $this->streamOpener,
            $this->realpathCache,
            $this->statCache,
            $this->contentsCache,
            pathFilter: new FileFilter('/my/**'),
            asVirtualFilesystem: false
        );
    }

    public function testCacheRealpathForwardsOntoRealpathCache(): void
    {
        $this->realpathCache->expects()
            ->cacheRealpath(
                '/my/custom/canonical/path',
                '/my/custom/real/path'
            )
            ->once();

        $this->fsCachingStreamHandler->cacheRealpath(
            canonicalPath: '/my/custom/canonical/path',
            realpath: '/my/custom/real/path'
        );
    }

    public function testGetRealpathForwardsOntoRealpathCache(): void
    {
        $this->realpathCache->allows()
            ->getRealpath('/my/custom/canonical/path')
            ->andReturn('/my/custom/real/path');

        static::assertSame(
            '/my/custom/real/path',
            $this->fsCachingStreamHandler->getRealpath('/my/custom/canonical/path')
        );
    }

    public function testInvalidatePathInvalidatesSubCachesWhenMatchedByPathFilter(): void
    {
        $this->realpathCache->allows()
            ->getCachedEventualPath('/my/canonical/path/to/my_file.txt')
            ->andReturn('/my/real/path/to/my_file.txt');

        $this->realpathCache->expects()
            ->invalidatePath('/my/canonical/path/to/my_file.txt')
            ->once();
        $this->statCache->expects()
            ->invalidatePath('/my/canonical/path/to/my_file.txt')
            ->once();
        $this->contentsCache->expects()
            ->invalidatePath('/my/canonical/path/to/my_file.txt')
            ->once();

        $this->fsCachingStreamHandler->invalidatePath('/my/canonical/path/to/my_file.txt');
    }

    public function testInvalidatePathDoesNotInvalidateSubCachesWhenIgnoredByPathFilter(): void
    {
        $this->realpathCache->allows()
            ->getCachedEventualPath('/your/canonical/path/to/your_file.txt')
            ->andReturn('/your/real/path/to/your_file.txt');

        $this->realpathCache->expects('invalidatePath')
            ->never();
        $this->statCache->expects('invalidatePath')
            ->never();
        $this->contentsCache->expects('invalidatePath')
            ->never();

        $this->fsCachingStreamHandler->invalidatePath('/your/canonical/path/to/your_file.txt');
    }

    public function testPersistStatCachePersistsStatCache(): void
    {
        $this->statCache->expects()
            ->persistStatCache()
            ->once();

        $this->fsCachingStreamHandler->persistStatCache();
    }

    public function testStreamOpenForwardsToWrappedHandlerWhenIgnoredByPathFilter(): void
    {
        $this->realpathCache->allows()
            ->getEventualPath('/your/canonical/path/to/your_file.php')
            ->andReturn('/your/real/path/to/your_file.php');
        $openedPath = null;
        $this->wrappedStreamHandler->allows()
            ->streamOpen(
                $this->streamWrapper,
                // Use `/your/**` path so that configured path filter is not matched.
                '/your/canonical/path/to/your_file.php',
                'r',
                0,
                $openedPath
            )
            ->andReturn(['resource' => 21, 'isInclude' => true]);

        static::assertEquals(
            ['resource' => 21, 'isInclude' => true],
            $this->fsCachingStreamHandler->streamOpen(
                $this->streamWrapper,
                '/your/canonical/path/to/your_file.php',
                'r',
                0,
                $openedPath
            )
        );
    }

    public function testStreamOpenForwardsToStreamOpenerWhenMatchedByPathFilter(): void
    {
        $this->realpathCache->allows()
            ->getEventualPath('/my/canonical/path/to/my_file.php')
            ->andReturn('/my/real/path/to/my_file.php');
        $openedPath = null;
        $this->streamOpener->allows()
            ->openStream(
                $this->streamWrapper,
                '/my/real/path/to/my_file.php',
                'r',
                0,
                $openedPath
            )
            ->andReturn(['resource' => 21, 'isInclude' => true]);

        static::assertEquals(
            ['resource' => 21, 'isInclude' => true],
            $this->fsCachingStreamHandler->streamOpen(
                $this->streamWrapper,
                '/my/canonical/path/to/my_file.php',
                'r',
                0,
                $openedPath
            )
        );
    }

    public function testStreamStatRetrievesACachedStat(): void
    {
        $this->streamWrapper->allows()
            ->getOpenPath()
            ->andReturn('/my/canonical/path/to/my_module.php');
        $this->realpathCache->allows()
            ->getEventualPath('/my/canonical/path/to/my_module.php')
            ->andReturn('/my/real/path/to/my_module.php');
        $this->statCache->allows()
            ->getStreamStat($this->streamWrapper)
            ->andReturn(['my_fake_include_stat' => 'yes']);

        static::assertEquals(
            ['my_fake_include_stat' => 'yes'],
            $this->fsCachingStreamHandler->streamStat($this->streamWrapper)
        );
    }

    public function testUrlStatRetrievesACachedPlainStat(): void
    {
        $this->realpathCache->allows()
            ->getEventualPath('/my/canonical/file.txt')
            ->andReturn('/my/real/file.txt');
        $this->statCache->allows()
            ->getPathStat('/my/canonical/file.txt', false, false)
            ->andReturn(['my_fake_plain_stat' => 'yes']);

        static::assertEquals(
            ['my_fake_plain_stat' => 'yes'],
            $this->fsCachingStreamHandler->urlStat('/my/canonical/file.txt', 0)
        );
    }
}
