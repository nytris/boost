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

/**
 * Interface BoostInterface.
 *
 * Defines the public facade API for the library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface BoostInterface
{
    /**
     * Installs Nytris Boost.
     */
    public function install(): void;

    /**
     * Uninstalls Nytris Boost.
     */
    public function uninstall(): void;
}
