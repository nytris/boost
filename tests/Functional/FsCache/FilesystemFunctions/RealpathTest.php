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

use Nytris\Boost\Tests\Functional\Fixtures\HookedLogic;

/**
 * Class RealpathTest.
 *
 * Tests the behaviour of the realpath(...) built-in function with Nytris Boost.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class RealpathTest extends AbstractFilesystemFunctionalTestCase
{
    public function testUsesPreviouslyCachedRealpath(): void
    {
        $targetPath = $this->varPath . '/my-file';
        $symlinkPath = $this->varPath . '/my-symlink';
        file_put_contents($targetPath, 'my contents');
        symlink($targetPath, $symlinkPath);
        $this->boost->install();
        $hookedLogic = new HookedLogic();

        // See notes in HookedLogic for why `realpath(...)` cannot be called directly here.
        static::assertSame($targetPath, $hookedLogic->callRealpath($symlinkPath));
        // Delete the file without using unlink(...) as that will be hooked.
        static::assertSame('', exec('rm ' . escapeshellarg($targetPath), result_code: $exitCode));
        static::assertSame(0, $exitCode);
        static::assertSame(
            $targetPath,
            $hookedLogic->callRealpath($symlinkPath),
            'Cached realpath should be returned'
        );
    }

    public function testInvalidatesCacheWhenNeeded(): void
    {
        $targetPath = $this->varPath . '/my-file';
        $symlinkPath1 = $this->varPath . '/my-symlink-1';
        $symlinkPath2 = $this->varPath . '/my-symlink-2';
        file_put_contents($targetPath, 'my first contents');
        symlink($targetPath, $symlinkPath1);
        $this->boost->install();
        $hookedLogic = new HookedLogic();

        // See notes in HookedLogic for why `realpath(...)` cannot be called directly here.
        static::assertSame($targetPath, $hookedLogic->callRealpath($symlinkPath1));
        unlink($targetPath);
        static::assertFalse($hookedLogic->callRealpath($symlinkPath1));
        symlink($targetPath, $symlinkPath2);
        file_put_contents($targetPath, 'my second contents'); // Cause cache to be cleared for this path.
        static::assertSame($targetPath, $hookedLogic->callRealpath($symlinkPath2));
        // Delete the file without using unlink(...) as that will be hooked.
        static::assertSame('', exec('rm ' . escapeshellarg($targetPath), result_code: $exitCode));
        static::assertSame(0, $exitCode);

        static::assertSame(
            $targetPath,
            $hookedLogic->callRealpath($symlinkPath2),
            'Cached realpath should be returned'
        );
    }

    public function testReturnsFalseForNonExistentFile(): void
    {
        $this->boost->install();
        $hookedLogic = new HookedLogic();

        // See notes in HookedLogic for why `realpath(...)` cannot be called directly here.
        static::assertFalse($hookedLogic->callRealpath('/my/non/existent/file'));
    }
}
