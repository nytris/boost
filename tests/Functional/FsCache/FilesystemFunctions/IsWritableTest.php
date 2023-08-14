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

namespace Nytris\Boost\Tests\Functional\FsCache\FilesystemFunctions;

/**
 * Class IsWritableTest.
 *
 * Tests the behaviour of the is_writable(...) built-in function with Nytris Boost.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class IsWritableTest extends AbstractFilesystemFunctionalTestCase
{
    public function testReturnsTrueForAWritableDirectory(): void
    {
        $path = $this->varPath . '/my-dir';
        $this->boost->install();
        is_dir($path); // Populate caches first to check invalidation.
        mkdir($path);

        static::assertTrue(is_writable($path));
    }

    public function testReturnsTrueForASymlinkToAWritableDirectory(): void
    {
        $directoryPath = $this->varPath . '/my-dir';
        $symlinkPath = $this->varPath . '/my-dir-symlink';
        symlink($directoryPath, $symlinkPath);
        $this->boost->install();
        is_dir($directoryPath); // Populate caches first
        is_dir($symlinkPath);   // to check invalidation.
        mkdir($directoryPath);

        static::assertTrue(is_writable($symlinkPath));
    }

    public function testReturnsFalseForANonExistentDirectory(): void
    {
        $this->boost->install();

        static::assertFalse(is_writable($this->varPath . '/non-existent-dir'));
    }
}
