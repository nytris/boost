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
 * Class LstatTest.
 *
 * Tests the behaviour of the lstat(...) built-in function with Nytris Boost.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class LstatTest extends AbstractFilesystemFunctionalTestCase
{
    public function testReturnsCorrectStatusOfANormalFileCreatedBeforeBoostWasInstalled(): void
    {
        $path = $this->varPath . '/my-file';
        file_put_contents($path, 'my contents');
        $actualStatus = lstat($path);
        $this->boost->install();

        $resultStatus = lstat($path);

        static::assertEquals($actualStatus, $resultStatus);
    }

    public function testReturnsCorrectStatusOfANormalFileCreatedAfterBoostWasInstalled(): void
    {
        $path = $this->varPath . '/my-file';
        $this->boost->install();
        file_put_contents($path, 'my contents');

        $resultStatus = lstat($path);
        $this->boost->uninstall();
        $actualStatus = lstat($path);

        static::assertEquals($actualStatus, $resultStatus);
    }

    public function testReturnsCorrectStatusOfADirectoryCreatedBeforeBoostWasInstalled(): void
    {
        $path = $this->varPath . '/my-dir';
        mkdir($path);
        $actualStatus = lstat($path);
        $this->boost->install();

        $resultStatus = lstat($path);

        static::assertEquals($actualStatus, $resultStatus);
    }

    public function testReturnsCorrectStatusOfADirectoryCreatedAfterBoostWasInstalled(): void
    {
        $path = $this->varPath . '/my-dir';
        $this->boost->install();
        mkdir($path);

        $resultStatus = lstat($path);
        $this->boost->uninstall();
        $actualStatus = lstat($path);

        static::assertEquals($actualStatus, $resultStatus);
    }

    public function testReturnsCorrectStatusOfAValidSymlinkCreatedBeforeBoostWasInstalled(): void
    {
        $eventualPath = $this->varPath . '/my-file';
        $symlinkPath = $this->varPath . '/my-symlink';
        file_put_contents($eventualPath, 'my contents');
        symlink($eventualPath, $symlinkPath);
        $actualStatus = lstat($symlinkPath);
        $this->boost->install();

        $resultStatus = lstat($symlinkPath);

        static::assertEquals($actualStatus, $resultStatus);
    }

    public function testReturnsCorrectStatusOfAValidSymlinkCreatedAfterBoostWasInstalled(): void
    {
        $eventualPath = $this->varPath . '/my-file';
        $symlinkPath = $this->varPath . '/my-symlink';
        file_put_contents($eventualPath, 'my contents');
        $this->boost->install();
        symlink($eventualPath, $symlinkPath);

        $resultStatus = lstat($symlinkPath);
        $this->boost->uninstall();
        $actualStatus = lstat($symlinkPath);

        static::assertEquals($actualStatus, $resultStatus);
    }

    public function testReturnsCorrectStatusOfAnInvalidSymlinkCreatedBeforeBoostWasInstalled(): void
    {
        $eventualPath = $this->varPath . '/my-file';
        $symlinkPath = $this->varPath . '/my-symlink';
        // (File is not created, leaving symlink pointing to a non-existent path.)
        symlink($eventualPath, $symlinkPath);
        $actualStatus = lstat($symlinkPath);
        $this->boost->install();

        $resultStatus = lstat($symlinkPath);

        static::assertEquals($actualStatus, $resultStatus);
    }

    public function testReturnsCorrectStatusOfAnInvalidSymlinkCreatedAfterBoostWasInstalled(): void
    {
        $eventualPath = $this->varPath . '/my-file';
        $symlinkPath = $this->varPath . '/my-symlink';
        // (File is not created, leaving symlink pointing to a non-existent path.)
        $this->boost->install();
        symlink($eventualPath, $symlinkPath);

        $resultStatus = lstat($symlinkPath);
        $this->boost->uninstall();
        $actualStatus = lstat($symlinkPath);

        static::assertEquals($actualStatus, $resultStatus);
    }

    public function testReturnsFalseForANonExistentFile(): void
    {
        $path = $this->varPath . '/my-file';
        $this->boost->install();

        static::assertFalse(@lstat($path));
    }
}
