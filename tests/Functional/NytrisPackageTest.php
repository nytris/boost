<?php

/*
 * Nytris Boost.
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/nytris/boost/
 *
 * Released under the MIT license.
 * https://github.com/nytris/boost/raw/main/MIT-LICENSE.txt
 */

declare(strict_types=1);

namespace Nytris\Boost\Tests\Functional;

use Asmblah\PhpCodeShift\CodeShift;
use Asmblah\PhpCodeShift\CodeShiftInterface;
use Asmblah\PhpCodeShift\Shift;
use Asmblah\PhpCodeShift\Shifter\Shift\Shift\String\StringLiteralShiftSpec;
use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapper;
use Asmblah\PhpCodeShift\ShiftPackage;
use Nytris\Boost\BoostPackage;
use Nytris\Boost\Charge;
use Nytris\Boost\Library\Library;
use Nytris\Boot\BootConfig;
use Nytris\Boot\PlatformConfig;
use Nytris\Nytris;

/**
 * Class NytrisPackageTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class NytrisPackageTest extends AbstractFunctionalTestCase
{
    private CodeShiftInterface $codeShift;
    private string $varPath;

    public function setUp(): void
    {
        $this->varPath = dirname(__DIR__, 2) . '/var/test';
        @mkdir($this->varPath, recursive: true);

        $this->codeShift = new CodeShift();

        Nytris::uninitialise();
        Nytris::initialise();
        Shift::uninstall();

        $bootConfig = new BootConfig(
            new PlatformConfig(baseCachePath: $this->varPath)
        );
        $bootConfig->installPackage(new ShiftPackage());
        $bootConfig->installPackage(new BoostPackage());

        Nytris::boot($bootConfig);
    }

    public function tearDown(): void
    {
        Shift::uninstall();
        Nytris::uninitialise();
        $this->codeShift->uninstall();

        $this->rimrafDescendantsOf($this->varPath);
    }

    public function testLibraryIsInstalledCorrectly(): void
    {
        static::assertInstanceOf(Library::class, Charge::getLibrary());
    }

    public function testShiftStreamWrapperIsInstalledCorrectly(): void
    {
        $stream = fopen($this->varPath . '/my_file.txt', 'wb+');

        $metaData = stream_get_meta_data($stream);

        static::assertSame('user-space', $metaData['wrapper_type']);
        static::assertInstanceOf(StreamWrapper::class, $metaData['wrapper_data']);
    }

    public function testShiftsMayBeAppliedToCachedModulesWithShiftThenWriteThenIncludeThenStat(): void
    {
        $modulePath = $this->varPath . '/my_module.php';
        $this->codeShift->shift(new StringLiteralShiftSpec('hello there', 'well good day to you'));
        file_put_contents($modulePath, <<<PHP
<?php

return 'I said hello there';
PHP);

        static::assertSame(
            'I said well good day to you',
            include $modulePath
        );
        $stat = stat($modulePath);
        static::assertSame(35, $stat['size']);
    }

    public function testShiftsMayBeAppliedToCachedModulesWithWriteThenShiftThenIncludeThenStat(): void
    {
        $modulePath = $this->varPath . '/my_module.php';
        file_put_contents($modulePath, <<<PHP
<?php

return 'I said hello there';
PHP);
        $this->codeShift->shift(new StringLiteralShiftSpec('hello there', 'well good day to you'));

        static::assertSame(
            'I said well good day to you',
            include $modulePath
        );
        $stat = stat($modulePath);
        static::assertSame(35, $stat['size']);
    }

    public function testShiftsMayBeAppliedToCachedModulesWithShiftThenWriteThenStatThenInclude(): void
    {
        $modulePath = $this->varPath . '/my_module.php';
        $this->codeShift->shift(new StringLiteralShiftSpec('hello there', 'well good day to you'));
        file_put_contents($modulePath, <<<PHP
<?php

return 'I said hello there';
PHP);
        $stat = stat($modulePath);

        static::assertSame(
            'I said well good day to you',
            include $modulePath
        );
        static::assertSame(35, $stat['size']);
    }
}
