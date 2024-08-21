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

use Nytris\Boost\BoostInterface;

/**
 * Class Library.
 *
 * Encapsulates an installation of the library as a Nytris package.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class Library implements LibraryInterface
{
    public function __construct(
        private readonly BoostInterface $boost
    ) {
        $boost->install();
    }

    /**
     * @inheritDoc
     */
    public function uninstall(): void
    {
        $this->boost->uninstall();
    }
}
