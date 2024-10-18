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

namespace Nytris\Boost\FsCache\Directory\Directory;

/**
 * Interface DirectoryInterface.
 *
 * Represents a directory in the directory cache.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface DirectoryInterface
{
    /**
     * Appends a filename to the cached entries of the directory.
     */
    public function appendEntry(string $filename): void;

    /**
     * Closes the directory.
     */
    public function close(): bool;

    /**
     * Fetches the cached entries of the directory.
     *
     * @return string[] Directory entry file/directory names.
     */
    public function getEntries(): array;

    /**
     * Fetches the next cached directory entry, or null if there is none.
     */
    public function getNextEntry(): ?string;

    /**
     * Determines whether the directory has yet been cached.
     */
    public function isCached(): bool;

    /**
     * Rewinds the directory pointer back to the first entry.
     */
    public function rewind(): void;

    /**
     * Updates the cached entries of the directory.
     *
     * @param string[] $filenames
     */
    public function setEntries(array $filenames): void;
}
