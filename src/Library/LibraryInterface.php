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

namespace Nytris\Boost\Library;

use Asmblah\PhpCodeShift\Shifter\Filter\FileFilterInterface;
use Nytris\Boost\BoostInterface;
use Nytris\Boost\Environment\EnvironmentInterface;
use Nytris\Boost\FsCache\CanonicaliserInterface;
use Nytris\Boost\FsCache\Realpath\RealpathCacheInterface;
use Nytris\Boost\FsCache\Stat\StatCacheInterface;

/**
 * Interface LibraryInterface.
 *
 * Encapsulates an installation of the library.
 *
 * @phpstan-import-type MultipleStatCacheStorage from StatCacheInterface
 * @phpstan-import-type RealpathCacheStorage from RealpathCacheInterface
 * @author Dan Phillimore <dan@ovms.co>
 */
interface LibraryInterface
{
    /**
     * Adds an instance of Boost to the library installation.
     */
    public function addBoost(BoostInterface $boost): void;

    /**
     * Fetches all registered Boost instances for the library installation.
     *
     * @return BoostInterface[]
     */
    public function getBoosts(): array;

    /**
     * Fetches the Canonicaliser.
     */
    public function getCanonicaliser(): CanonicaliserInterface;

    /**
     * Fetches the Environment.
     */
    public function getEnvironment(): EnvironmentInterface;

    /**
     * Fetches the in-memory realpath entry cache across all registered Boost instances.
     *
     * @return RealpathCacheStorage
     */
    public function getInMemoryRealpathEntryCache(): array;

    /**
     * Fetches the in-memory stat entry cache across all registered Boost instances.
     *
     * @return MultipleStatCacheStorage
     */
    public function getInMemoryStatEntryCache(): array;

    /**
     * Hooks built-in functions to make them compatible with Nytris Boost.
     */
    public function hookBuiltinFunctions(FileFilterInterface $fileFilter): void;

    /**
     * Removes an instance of Boost from the library installation.
     */
    public function removeBoost(BoostInterface $boost): void;

    /**
     * Uninstalls this installation of the library.
     */
    public function uninstall(): void;
}
