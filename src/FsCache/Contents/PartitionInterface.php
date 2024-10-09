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
 * Interface PartitionInterface.
 *
 * Represents a separate cache area to store contents for certain paths.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface PartitionInterface
{
    /**
     * Fetches the cache item for the given path and plain file or include mode.
     *
     * Returns null if this partition is not responsible for the given path.
     */
    public function getItemForPath(
        string $path,
        bool $isInclude,
        ContentsCacheInterface $contentsCache
    ): ?CachedFileInterface;

    /**
     * Invalidates any content cache entries in this partition for the given path.
     *
     * Returns true if the path is within this partition, false otherwise.
     */
    public function invalidatePath(string $path, ContentsCacheInterface $contentsCache): bool;
}
