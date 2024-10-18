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

namespace Nytris\Boost\FsCache\Directory\Directory;

use LogicException;
use Psr\Cache\CacheItemInterface;

/**
 * Class CachedDirectory.
 *
 * Represents a directory that has been fetched from the directory cache.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class CachedDirectory implements DirectoryInterface
{
    private int $currentIndex = -1;
    /**
     * @var string[]
     */
    private array $entries;

    public function __construct(
        CacheItemInterface $cachePoolItem
    ) {
        if (!$cachePoolItem->isHit()) {
            throw new LogicException('CachedDirectory cannot be used for a cache miss');
        }

        $this->entries = $cachePoolItem->get();
    }

    /**
     * @inheritDoc
     */
    public function appendEntry(string $filename): void
    {
        throw new LogicException('CachedDirectory entries cannot be appended to');
    }

    /**
     * @inheritDoc
     */
    public function close(): bool
    {
        return true; // A cached directory has no open underlying directory handle to close.
    }

    /**
     * @inheritDoc
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * @inheritDoc
     */
    public function getNextEntry(): ?string
    {
        return $this->entries[++$this->currentIndex] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function isCached(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        $this->currentIndex = -1;
    }

    /**
     * @inheritDoc
     */
    public function setEntries(array $filenames): void
    {
        throw new LogicException('CachedDirectory entries cannot be replaced');
    }
}
