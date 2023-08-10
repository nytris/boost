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

/**
 * Interface FsCacheInterface.
 *
 * Emulates the PHP realpath and stat caches in userland, even when open_basedir is enabled.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface FsCacheInterface
{
    public const DEFAULT_REALPATH_CACHE_KEY = '__nytris_boost_realpath_cache';
    public const DEFAULT_STAT_CACHE_KEY = '__nytris_boost_stat_cache';

    /**
     * Installs the filesystem cache.
     */
    public function install(): void;

    /**
     * Uninstalls the filesystem cache.
     */
    public function uninstall(): void;
}
