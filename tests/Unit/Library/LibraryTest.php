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

namespace Nytris\Boost\Tests\Unit\Library;

use Asmblah\PhpCodeShift\CodeShiftInterface;
use Asmblah\PhpCodeShift\Shifter\Filter\FileFilterInterface;
use Asmblah\PhpCodeShift\Shifter\Shift\Shift\FunctionHook\FunctionHookShiftSpec;
use Asmblah\PhpCodeShift\Shifter\Shift\Spec\ShiftSpecInterface;
use Mockery\MockInterface;
use Nytris\Boost\BoostInterface;
use Nytris\Boost\Environment\EnvironmentInterface;
use Nytris\Boost\FsCache\CanonicaliserInterface;
use Nytris\Boost\Library\Library;
use Nytris\Boost\Tests\AbstractTestCase;

/**
 * Class LibraryTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class LibraryTest extends AbstractTestCase
{
    private MockInterface&CanonicaliserInterface $canonicaliser;
    private MockInterface&CodeShiftInterface $codeShift;
    private MockInterface&EnvironmentInterface $environment;
    private Library $library;

    public function setUp(): void
    {
        $this->canonicaliser = mock(CanonicaliserInterface::class);
        $this->codeShift = mock(CodeShiftInterface::class, [
            'deny' => null,
            'install' => null,
        ]);
        $this->environment = mock(EnvironmentInterface::class);

        $this->library = new Library(
            $this->environment,
            $this->canonicaliser,
            $this->codeShift
        );
    }

    public function testGetBoostsReturnsEmptyListInitially(): void
    {
        static::assertEquals([], $this->library->getBoosts());
    }

    // This is so that the most recently-registered instance, which should be the assigned one,
    // is checked first when resolving realpaths.
    public function testGetBoostsReturnsAllRegisteredInstancesInReverseOrder(): void
    {
        $boost1 = mock(BoostInterface::class);
        $boost2 = mock(BoostInterface::class);
        $this->library->addBoost($boost1);
        $this->library->addBoost($boost2);

        static::assertSame([$boost2, $boost1], $this->library->getBoosts());
    }

    public function testHookBuiltinFunctionsReturnsRealpathFromMostRecentBoostInstance(): void
    {
        $fileFilter = mock(FileFilterInterface::class);
        $this->library->addBoost(mock(BoostInterface::class));
        $boost = mock(BoostInterface::class);
        $this->library->addBoost($boost);
        $boost->allows()
            ->getRealpath('/my/path')
            ->andReturn('/my/realpath');

        $this->codeShift->expects('shift')
            ->once();
        $this->codeShift->expects('shift')
            ->once();
        $this->codeShift->expects('shift')
            ->once()
            ->andReturnUsing(function (ShiftSpecInterface $shiftSpec) {
                /** @var FunctionHookShiftSpec $shiftSpec */
                static::assertInstanceOf(FunctionHookShiftSpec::class, $shiftSpec);
                static::assertSame('realpath', $shiftSpec->getFunctionName());
                $realpathReplacement = $shiftSpec->getReplacementProvider()();
                static::assertSame('/my/realpath', $realpathReplacement('/my/path'));
            });

        $this->library->hookBuiltinFunctions($fileFilter);
    }

    public function testRemoveBoostRemovesFromTheList(): void
    {
        $boost1 = mock(BoostInterface::class, [
            'install' => null,
        ]);
        $boost2 = mock(BoostInterface::class);
        $this->library->addBoost($boost1);
        $this->library->addBoost($boost2);

        $this->library->removeBoost($boost2);

        static::assertSame([$boost1], $this->library->getBoosts());
    }

    public function testRemoveBoostUninstallsCodeShiftWhenThereAreNoBoostInstancesLeft(): void
    {
        $boost1 = mock(BoostInterface::class, [
            'install' => null,
        ]);
        $boost2 = mock(BoostInterface::class);
        $this->library->addBoost($boost1);
        $this->library->addBoost($boost2);

        $this->library->removeBoost($boost2);
        $this->codeShift->expects()
            ->uninstall()
            ->once();
        $this->library->removeBoost($boost1);
    }
}
