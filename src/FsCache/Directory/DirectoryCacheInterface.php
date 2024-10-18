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

namespace Nytris\Boost\FsCache\Directory;

use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapperInterface;
use Nytris\Boost\FsCache\Directory\Directory\DirectoryInterface;

/**
 * Interface DirectoryCacheInterface.
 *
 * Caches directory entries vs. the realpath, stat and file contents caches.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface DirectoryCacheInterface
{
    public const DEFAULT_CACHE_KEY_PREFIX = '__nytris_boost_directory_cache_';

    /**
     * Hashes the given cache key to support arbitrary-length keys.
     */
    public function buildCacheKey(string $prefix, string $key): string;

    /**
     * Fetches the cache item for the given path.
     */
    public function getItemForPath(
        string $path,
        StreamWrapperInterface $streamWrapper,
        StreamHandlerInterface $wrappedStreamHandler
    ): DirectoryInterface;

    /**
     * Invalidates any directory cache entries for the given path.
     */
    public function invalidatePath(string $path): void;
}
