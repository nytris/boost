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
 * Class TempnamTest.
 *
 * Tests the behaviour of the tempnam(...) built-in function with Nytris Boost.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class TempnamTest extends AbstractFilesystemFunctionalTestCase
{
    public function testCreatesTemporaryFileInTheSpecifiedPath(): void
    {
        $directoryPath = $this->varPath . '/my-dir';
        mkdir($directoryPath);
        $this->boost->install();
        $hookedLogic = new HookedLogic();

        // See notes in HookedLogic for why `tempnam(...)` cannot be called directly here.
        $temporaryFilePath = $hookedLogic->callTempnam($directoryPath, 'my_prefix_');

        static::assertSame($directoryPath, dirname($temporaryFilePath));
        static::assertStringStartsWith('my_prefix_', basename($temporaryFilePath));
    }
}
