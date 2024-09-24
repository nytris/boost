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

/**
 * Interface LibraryInterface.
 *
 * Encapsulates an installation of the library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface LibraryInterface
{
    /**
     * Adds an instance of Boost to the library installation.
     */
    public function addBoost(BoostInterface $boost): void;

    /**
     * Fetches the Canonicaliser.
     */
    public function getCanonicaliser(): CanonicaliserInterface;

    /**
     * Fetches the Environment.
     */
    public function getEnvironment(): EnvironmentInterface;

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
