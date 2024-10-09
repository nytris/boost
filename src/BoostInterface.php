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

namespace Nytris\Boost;

use Nytris\Boost\FsCache\Realpath\RealpathCacheInterface;
use Nytris\Boost\FsCache\Stat\StatCacheInterface;
use Nytris\Boost\Library\LibraryInterface;

/**
 * Interface BoostInterface.
 *
 * Defines the public facade API for the library.
 *
 * @phpstan-import-type MultipleStatCacheStorage from StatCacheInterface
 * @phpstan-import-type RealpathCacheStorage from RealpathCacheInterface
 * @author Dan Phillimore <dan@ovms.co>
 */
interface BoostInterface
{
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
     * Fetches the library installation for this Boost instance.
     */
    public function getLibrary(): LibraryInterface;

    /**
     * Installs Nytris Boost.
     */
    public function install(): void;

    /**
     * Clears the realpath and stat caches.
     *
     * Note that this does not affect the contents cache.
     */
    public function invalidateCaches(): void;

    /**
     * Uninstalls Nytris Boost.
     */
    public function uninstall(): void;
}
