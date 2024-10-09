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
 * Interface ContentsCacheInterface.
 *
 * Caches file contents vs. the realpath and stat caches.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface ContentsCacheInterface
{
    public const DEFAULT_CACHE_KEY_PREFIX = '__nytris_boost_contents_cache_';

    /**
     * Hashes the given cache key to support arbitrary-length keys.
     *
     * Plain and include streams are cached separately, because include streams' contents
     * may differ from the originals due to code shifting.
     */
    public function buildCacheKey(string $prefix, string $key, bool $isInclude): string;

    /**
     * Fetches the cache item for the given path and plain file or include mode.
     */
    public function getItemForPath(string $path, bool $isInclude): CachedFileInterface;

    /**
     * Invalidates any content cache entries for the given path.
     */
    public function invalidatePath(string $path): void;
}
