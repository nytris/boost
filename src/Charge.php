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

use InvalidArgumentException;
use LogicException;
use Nytris\Boost\Library\Library;
use Nytris\Boost\Library\LibraryInterface;
use Nytris\Core\Package\PackageContextInterface;
use Nytris\Core\Package\PackageInterface;

/**
 * Class Charge.
 *
 * Defines the public facade API for the library as a Nytris package.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class Charge implements ChargeInterface
{
    private static ?LibraryInterface $library = null;

    /**
     * @inheritDoc
     */
    public static function getLibrary(): LibraryInterface
    {
        if (!self::$library) {
            throw new LogicException(
                'Library is not installed - did you forget to install this package in nytris.config.php?'
            );
        }

        return self::$library;
    }

    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return 'boost';
    }

    /**
     * @inheritDoc
     */
    public static function getVendor(): string
    {
        return 'nytris';
    }

    /**
     * @inheritDoc
     */
    public static function install(PackageContextInterface $packageContext, PackageInterface $package): void
    {
        if (!$package instanceof BoostPackageInterface) {
            throw new InvalidArgumentException(
                sprintf(
                    'Package config must be a %s but it was a %s',
                    BoostPackageInterface::class,
                    $package::class
                )
            );
        }

        if (self::$library === null) {
            self::$library = new Library();
        }

        $boost = new Boost(
            library: self::$library,
            realpathCachePool: $package->getRealpathCachePool($packageContext->getPackageCachePath()),
            statCachePool: $package->getStatCachePool($packageContext->getPackageCachePath()),
            realpathCacheKey: $package->getRealpathCacheKey(),
            statCacheKey: $package->getStatCacheKey(),
            hookBuiltinFunctions: $package->getHookBuiltinFunctionsFilter() ?? false,
            cacheNonExistentFiles: $package->shouldCacheNonExistentFiles(),
            contentsCache: $package->getContentsCache($packageContext->getPackageCachePath()),
            pathFilter: $package->getPathFilter(),
            asVirtualFilesystem: $package->isVirtualFilesystem()
        );

        $boost->install();
    }

    /**
     * @inheritDoc
     */
    public static function isInstalled(): bool
    {
        return self::$library !== null;
    }

    /**
     * @inheritDoc
     */
    public static function setLibrary(LibraryInterface $library): void
    {
        self::$library?->uninstall();

        self::$library = $library;
    }

    /**
     * @inheritDoc
     */
    public static function uninstall(): void
    {
        if (self::$library === null) {
            // Not yet installed anyway; nothing to do.
            return;
        }

        self::$library->uninstall();
        self::$library = null;
    }
}
