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

namespace Nytris\Boost\FsCache;

/**
 * Interface CanonicaliserInterface.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface CanonicaliserInterface
{
    /**
     * Canonicalises the given path, resolving all "./", "../", "//" symbols etc.
     * Similar to the built-in realpath(...) function, except symlinks are not resolved.
     */
    public function canonicalise(string $path, string $cwd): string;
}
