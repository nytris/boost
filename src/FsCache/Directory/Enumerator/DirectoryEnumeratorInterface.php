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

namespace Nytris\Boost\FsCache\Directory\Enumerator;

use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapperInterface;

/**
 * Interface DirectoryEnumeratorInterface.
 *
 * Enumerates directory entries.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface DirectoryEnumeratorInterface
{
    /**
     * Closes the given directory handle.
     */
    public function closeDirectory(StreamWrapperInterface $streamWrapper): bool;

    /**
     * Opens the given directory path for enumeration.
     *
     * @param StreamWrapperInterface $streamWrapper
     * @param string $path
     * @param int $options
     * @return resource|null
     */
    public function openDirectory(StreamWrapperInterface $streamWrapper, string $path, int $options);

    /**
     * Fetches the next entry for the given directory handle, or null if none remain.
     *
     * @param StreamWrapperInterface $streamWrapper
     * @return ?string
     */
    public function readDirectory(StreamWrapperInterface $streamWrapper);
}
