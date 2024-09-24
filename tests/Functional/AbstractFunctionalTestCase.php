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

namespace Nytris\Boost\Tests\Functional;

use Nytris\Boost\FsCache\CanonicaliserInterface;
use Nytris\Boost\Tests\AbstractTestCase;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class AbstractFunctionalTestCase.
 *
 * Base class for all functional test cases.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
abstract class AbstractFunctionalTestCase extends AbstractTestCase
{
    protected CanonicaliserInterface $canonicaliser;

    protected function getRealpathPsrCacheItem(
        CacheItemPoolInterface $cachePool,
        string $key
    ): mixed {
        $item = $cachePool->getItem($this->canonicaliser->canonicaliseCacheKey($key));

        return $item->isHit() ? $item->get() : null;
    }

    protected function rimrafDescendantsOf(string $path): void
    {
        foreach (glob($path . '/**') as $subPath) {
            if (is_file($subPath) || is_link($subPath)) {
                unlink($subPath);
            } else {
                $this->rimrafDescendantsOf($subPath);

                rmdir($subPath);
            }
        }
    }

    protected function setRealpathPsrCacheItem(
        CacheItemPoolInterface $cachePool,
        string $key,
        mixed $value
    ): void {
        $item = $cachePool->getItem($this->canonicaliser->canonicaliseCacheKey($key));

        $item->set($value);
        $cachePool->save($item);
    }

    protected function setStatPsrCacheItem(
        CacheItemPoolInterface $cachePool,
        string $key,
        bool $isInclude,
        mixed $value
    ): void {
        $cacheKey = ($isInclude ? 'include_' : 'plain_') . $this->canonicaliser->canonicaliseCacheKey($key);
        $item = $cachePool->getItem($cacheKey);

        $item->set($value);
        $cachePool->save($item);
    }
}
