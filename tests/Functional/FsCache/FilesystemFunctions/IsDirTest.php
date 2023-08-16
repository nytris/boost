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
 * Class IsDirTest.
 *
 * Tests the behaviour of the is_dir(...) built-in function with Nytris Boost.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class IsDirTest extends AbstractFilesystemFunctionalTestCase
{
    public function testReturnsTrueForAnExistentDirectory(): void
    {
        $path = $this->varPath . '/my-dir';
        mkdir($path);
        $this->boost->install();

        static::assertTrue(is_dir($path));
    }

    public function testReturnsFalseForANonExistentDirectory(): void
    {
        $this->boost->install();

        static::assertFalse(is_dir($this->varPath . '/non-existent-dir'));
    }
}
