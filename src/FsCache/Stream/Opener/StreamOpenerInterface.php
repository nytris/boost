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

namespace Nytris\Boost\FsCache\Stream\Opener;

use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapperInterface;
use Nytris\Boost\FsCache\Stream\Handler\FsCachingStreamHandlerInterface;

/**
 * Interface StreamOpenerInterface.
 *
 * Opens a file stream, handling contents caching if applicable.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface StreamOpenerInterface
{
    /**
     * Opens a stream to the given file.
     *
     * Returns both the opened stream resource and whether this is an include vs. normal file access on success.
     * Returns null on failure.
     *
     * @return array{resource: resource|null, isInclude: bool}|null
     */
    public function openStream(
        StreamWrapperInterface $streamWrapper,
        string $path,
        string $mode,
        int $options,
        ?string &$openedPath,
        FsCachingStreamHandlerInterface $streamHandler
    ): ?array;
}
