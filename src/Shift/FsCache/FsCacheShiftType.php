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

namespace Nytris\Boost\Shift\FsCache;

use Asmblah\PhpCodeShift\Shifter\Shift\Shift\ShiftTypeInterface;

/**
 * Class FsCacheShiftType.
 *
 * Emulates the PHP realpath cache in userland, even when open_basedir is enabled.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class FsCacheShiftType implements ShiftTypeInterface
{
    /**
     * @inheritDoc
     */
    public function getShifter(): callable
    {
        return $this->shift(...);
    }

    /**
     * @inheritDoc
     */
    public function getShiftSpecFqcn(): string
    {
        return FsCacheShiftSpec::class;
    }

    /**
     * Applies the shift to the contents.
     */
    public function shift(FsCacheShiftSpec $shiftSpec, string $contents): string
    {
        return $contents; // TODO.
    }
}
