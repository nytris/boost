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
 * Class StatTest.
 *
 * Tests the behaviour of the stat(...) built-in function with Nytris Boost.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class StatTest extends AbstractFilesystemFunctionalTestCase
{
    public function testReturnsCorrectStatusOfANormalFileCreatedBeforeBoostWasInstalled(): void
    {
        $path = $this->varPath . '/my-file';
        file_put_contents($path, 'my contents');
        $actualStatus = stat($path);
        $this->boost->install();

        $resultStatus = stat($path);

        static::assertEquals($actualStatus, $resultStatus);
    }

    public function testReturnsCorrectStatusOfANormalFileCreatedAfterBoostWasInstalled(): void
    {
        $path = $this->varPath . '/my-file';
        $this->boost->install();
        file_put_contents($path, 'my contents');

        $resultStatus = stat($path);
        $this->boost->uninstall();
        $actualStatus = stat($path);

        static::assertEquals($actualStatus, $resultStatus);
    }

    public function testReturnsCorrectStatusOfADirectoryCreatedBeforeBoostWasInstalled(): void
    {
        $path = $this->varPath . '/my-dir';
        mkdir($path);
        $actualStatus = stat($path);
        $this->boost->install();

        $resultStatus = stat($path);

        static::assertEquals($actualStatus, $resultStatus);
    }

    public function testReturnsCorrectStatusOfADirectoryCreatedAfterBoostWasInstalled(): void
    {
        $path = $this->varPath . '/my-dir';
        $this->boost->install();
        mkdir($path);

        $resultStatus = stat($path);
        $this->boost->uninstall();
        $actualStatus = stat($path);

        static::assertEquals($actualStatus, $resultStatus);
    }

    public function testReturnsCorrectStatusOfTargetOfAValidSymlinkCreatedBeforeBoostWasInstalled(): void
    {
        $eventualPath = $this->varPath . '/my-file';
        $symlinkPath = $this->varPath . '/my-symlink';
        file_put_contents($eventualPath, 'my contents');
        symlink($eventualPath, $symlinkPath);
        $actualStatus = stat($eventualPath);
        $this->boost->install();

        $resultStatus = stat($symlinkPath);

        static::assertEquals($actualStatus, $resultStatus);
    }

    public function testReturnsCorrectStatusOfTargetOfAValidSymlinkCreatedAfterBoostWasInstalled(): void
    {
        $eventualPath = $this->varPath . '/my-file';
        $symlinkPath = $this->varPath . '/my-symlink';
        file_put_contents($eventualPath, 'my contents');
        $this->boost->install();
        symlink($eventualPath, $symlinkPath);

        $resultStatus = stat($symlinkPath);
        $this->boost->uninstall();
        $actualStatus = stat($eventualPath);

        static::assertEquals($actualStatus, $resultStatus);
    }

    public function testReturnsFalseForAnInvalidSymlinkCreatedBeforeBoostWasInstalled(): void
    {
        $eventualPath = $this->varPath . '/my-file';
        $symlinkPath = $this->varPath . '/my-symlink';
        // (File is not created, leaving symlink pointing to a non-existent path.)
        symlink($eventualPath, $symlinkPath);
        $this->boost->install();

        $resultStatus = @stat($symlinkPath);

        static::assertFalse($resultStatus);
    }

    public function testReturnsFalseForAnInvalidSymlinkCreatedAfterBoostWasInstalled(): void
    {
        $eventualPath = $this->varPath . '/my-file';
        $symlinkPath = $this->varPath . '/my-symlink';
        // (File is not created, leaving symlink pointing to a non-existent path.)
        $this->boost->install();
        symlink($eventualPath, $symlinkPath);

        $resultStatus = @stat($symlinkPath);

        static::assertFalse($resultStatus);
    }

    public function testReturnsFalseForANonExistentFile(): void
    {
        $path = $this->varPath . '/my-file';
        $this->boost->install();

        static::assertFalse(@stat($path));
    }
}
