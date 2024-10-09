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

namespace Nytris\Boost\FsCache\Contents;

use LogicException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class CachedFile.
 *
 * Represents a file in the contents cache.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class CachedFile implements CachedFileInterface
{
    private bool $isCached;

    public function __construct(
        private readonly CacheItemPoolInterface $cachePool,
        private readonly CacheItemInterface $cachePoolItem
    ) {
        $this->isCached = $this->cachePoolItem->isHit();
    }

    /**
     * @inheritDoc
     */
    public function getContents(): string
    {
        if (!$this->isCached) {
            throw new LogicException('File contents are not yet cached');
        }

        return $this->cachePoolItem->get();
    }

    /**
     * @inheritDoc
     */
    public function isCached(): bool
    {
        return $this->isCached;
    }

    /**
     * @inheritDoc
     */
    public function setContents(string $contents): void
    {
        $this->cachePoolItem->set($contents);
        $this->cachePool->saveDeferred($this->cachePoolItem);

        $this->isCached = true;
    }
}
