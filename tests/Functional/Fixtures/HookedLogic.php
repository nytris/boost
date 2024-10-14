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

namespace Nytris\Boost\Tests\Functional\Fixtures;

/**
 * Class HookedLogic.
 *
 * A module that may be transpiled by PHP Code Shift, to allow testing of hooked functions
 * when enabled, such as `clearstatcache(...)`.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class HookedLogic
{
    public function callClearstatcache(): void
    {
        clearstatcache();
    }

    public function callOpcacheInvalidate(string $filename, bool $force = false): bool
    {
        return opcache_invalidate($filename, force: $force);
    }

    public function callRealpath(string $path): string|false
    {
        return realpath($path);
    }
}
