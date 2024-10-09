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
use Nytris\Core\Package\PackageFacadeInterface;

/**
 * Interface ChargeInterface.
 *
 * Defines the public facade API for the library as a Nytris package.
 *
 * @phpstan-import-type MultipleStatCacheStorage from StatCacheInterface
 * @phpstan-import-type RealpathCacheStorage from RealpathCacheInterface
 * @author Dan Phillimore <dan@ovms.co>
 */
interface ChargeInterface extends PackageFacadeInterface
{
    /**
     * Fetches the in-memory realpath entry cache across all registered Boost instances.
     *
     * @return RealpathCacheStorage
     */
    public static function getInMemoryRealpathEntryCache(): array;

    /**
     * Fetches the in-memory stat entry cache across all registered Boost instances.
     *
     * @return MultipleStatCacheStorage
     */
    public static function getInMemoryStatEntryCache(): array;

    /**
     * Fetches the current library installation.
     */
    public static function getLibrary(): LibraryInterface;

    /**
     * Overrides the current library installation with the given one.
     */
    public static function setLibrary(LibraryInterface $library): void;
}
