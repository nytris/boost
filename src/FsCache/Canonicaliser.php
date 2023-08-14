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
 * Class Canonicaliser.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class Canonicaliser implements CanonicaliserInterface
{
    /**
     * @inheritDoc
     */
    public function canonicalise(string $path, string $cwd): string
    {
        // Resolve same- or parent directory prefix segment.
        if (str_starts_with($path, './') || str_starts_with($path, '../')) {
            $path = $cwd . '/' . $path;
        }

        // Resolve redundant dot same-directory symbols "a/./b".
        $path = preg_replace('#(?:^|/)(?:\./)+#', '/', $path);

        // Resolve redundant slash same-directory symbols "a//b".
        $path = preg_replace('#/{2,}#', '/', $path);

        // Resolve parent directory symbols "a/../b".
        $count = 0;

        do {
            $path = preg_replace('#(?:^|/|\G)(?!\.\./)([^/]+)/\.\./#', '/', $path, count: $count);
        } while ($count > 0);

        return $path;
    }
}
