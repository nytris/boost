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
 * Class ClearstatcacheTest.
 *
 * Tests the behaviour of the clearstatcache(...) built-in function with Nytris Boost.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ClearstatcacheTest extends AbstractFilesystemFunctionalTestCase
{
    public function testClearsBoostStatCache(): void
    {
        $path = $this->varPath . '/my-file';
        file_put_contents($path, 'my contents');
        $this->boost->install();

        $stat = stat($path);
        static::assertSame(11, $stat['size']);
        static::assertSame('', exec(
            'echo " [and extra]" >> ' . escapeshellarg($path),
            result_code: $exitCode
        ));
        static::assertSame(0, $exitCode);
        $stat = stat($path);
        static::assertSame(11, $stat['size'], 'Cached stat with old length should be returned');
        // See notes in HookedLogic for why `clearstatcache(...)` cannot be called directly here.
        (new HookedLogic())->callClearstatcache();
        $stat = stat($path);
        static::assertSame(24, $stat['size'], 'New stat with new length should be returned');
    }
}
