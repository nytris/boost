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

    public function testReturnsTrueWhenOnlyAnAclAllowsWriteAccess(): void
    {
        $filePath = $this->varPath . '/my-file';
        touch($filePath);
        chmod($filePath, 0500); // Remove all write access.
        $user = get_current_user();
        $exitCode = 0;
        // Grant the current user write access via ACL only.
        $this->grantWriteAccessViaAcl($user, $filePath);
        $this->boost->install();

        static::assertSame(0, $exitCode);
        static::assertTrue(is_writable($filePath));
        clearstatcache(); // See caveat in FsCachingStreamHandler.
        static::assertTrue(is_writeable($filePath)); // Check the alias as well.
    }

    public function testReturnsTrueFollowingOtherStatBasedFunctionsWhenOnlyAnAclAllowsWriteAccess(): void
    {
        $filePath = $this->varPath . '/my-file';
        touch($filePath);
        chmod($filePath, 0500); // Remove all write access.
        $user = get_current_user();
        $exitCode = 0;
        // Grant the current user write access via ACL only.
        $this->grantWriteAccessViaAcl($user, $filePath);
        $this->boost->install();

        static::assertSame(0, $exitCode);
        static::assertTrue(
            /*
             * Populate PHP's internal stat cache immediately before calling is_writable(...),
             * so that any test runner -related autoloading etc. is not performed in-between
             * that may clobber the internal stat cache.
             */
            is_file($filePath) &&
            is_writable($filePath)
        );
        clearstatcache(); // See caveat in FsCachingStreamHandler.
        static::assertTrue(is_writeable($filePath)); // Check the alias as well.
    }

    /**
     * Grants write access via ACL in a cross-platform way.
     */
    private function grantWriteAccessViaAcl(string $user, string $path): void
    {
        exec('command -v setfacl', result_code: $exitCode);
        $setfaclSupported = $exitCode === 0;

        if ($setfaclSupported) {
            // Explicitly set the mask because the Unix permissions may deliberately be set to disallow,
            // and so the auto-calculated mask would otherwise block the allow ACL we're trying to add.
            exec(
                'setfacl -m u:' . $user . ':w -m:rwx ' . escapeshellarg($path),
                result_code: $exitCode
            );

            if ($exitCode !== 0) {
                $this->fail('setfacl failed');
            }
        } else {
            exec(
                'chmod +a "' . $user . ' allow write" ' . escapeshellarg($path),
                result_code: $exitCode
            );

            if ($exitCode !== 0) {
                $this->fail('chmod failed');
            }
        }
    }

    public function testReturnsFalseForANonExistentDirectory(): void
    {
        $this->boost->install();

        static::assertFalse(is_writable($this->varPath . '/non-existent-dir'));
    }
}
