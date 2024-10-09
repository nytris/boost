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

namespace Nytris\Boost\FsCache;

use Nytris\Boost\FsCache\Realpath\RealpathCacheInterface;
use Nytris\Boost\FsCache\Stat\StatCacheInterface;

/**
 * Interface FsCacheInterface.
 *
 * Emulates the PHP realpath and stat caches in userland, even when open_basedir is enabled.
 *
 * @phpstan-import-type MultipleStatCacheStorage from StatCacheInterface
 * @phpstan-import-type RealpathCacheStorage from RealpathCacheInterface
 * @author Dan Phillimore <dan@ovms.co>
 */
interface FsCacheInterface
{
    public const DEFAULT_REALPATH_CACHE_KEY = '__nytris_boost_realpath_cache';
    public const DEFAULT_STAT_CACHE_KEY = '__nytris_boost_stat_cache';

    /**
     * Fetches the in-memory realpath entry cache.
     *
     * @return RealpathCacheStorage
     */
    public function getInMemoryRealpathEntryCache(): array;

    /**
     * Fetches the in-memory stat entry cache.
     *
     * @return MultipleStatCacheStorage
     */
    public function getInMemoryStatEntryCache(): array;

    /**
     * Installs the filesystem cache.
     */
    public function install(): void;

    /**
     * Clears the realpath and stat caches.
     *
     * Note that this does not affect the contents cache.
     */
    public function invalidateCaches(): void;

    /**
     * Uninstalls the filesystem cache.
     */
    public function uninstall(): void;
}
