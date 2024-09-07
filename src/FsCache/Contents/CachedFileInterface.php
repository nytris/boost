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

namespace Nytris\Boost\FsCache\Contents;

/**
 * Interface CachedFileInterface.
 *
 * Represents a file in the contents cache.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface CachedFileInterface
{
    /**
     * Fetches the cached contents of the file.
     */
    public function getContents(): string;

    /**
     * Determines whether the file has yet been cached.
     */
    public function isCached(): bool;

    /**
     * Updates the cached contents of the file.
     */
    public function setContents(string $contents): void;
}
