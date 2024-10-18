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

use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapperInterface;
use BadMethodCallException;
use LogicException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class FreshDirectory.
 *
 * Represents a directory that has not yet been persisted to the directory cache.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class FreshDirectory implements DirectoryInterface
{
    private int $currentIndex = -1;
    /**
     * @var string[]
     */
    private array $entries = [];

    public function __construct(
        private readonly StreamHandlerInterface $wrappedStreamHandler,
        private readonly StreamWrapperInterface $streamWrapper,
        private readonly CacheItemPoolInterface $cachePool,
        private readonly CacheItemInterface $cachePoolItem
    ) {
        if ($cachePoolItem->isHit()) {
            throw new LogicException('CachedDirectory cannot be used for a cache hit');
        }
    }

    /**
     * @inheritDoc
     */
    public function appendEntry(string $filename): void
    {
        $this->entries[] = $filename;

        $this->setEntries($this->entries);

        $this->currentIndex = count($this->entries) - 1;
    }

    /**
     * @inheritDoc
     */
    public function close(): bool
    {
        return $this->wrappedStreamHandler->closeDir($this->streamWrapper);
    }

    /**
     * @inheritDoc
     */
    public function getEntries(): array
    {
        throw new BadMethodCallException('Not implemented');
//        while ($this->getNextEntry()) {}
//
//        return $this->entries;
    }

    /**
     * @inheritDoc
     */
    public function getNextEntry(): ?string
    {
        if ($this->currentIndex < count($this->entries) - 1) {
            return $this->entries[++$this->currentIndex];
        }

        $filename = $this->wrappedStreamHandler->readDir($this->streamWrapper);

        if ($filename !== false) {
            $this->entries[] = $filename;
            ++$this->currentIndex;

            $this->cachePoolItem->set($this->entries);
            $this->cachePool->saveDeferred($this->cachePoolItem);
        }

        return $filename !== false ? $filename : null;
    }

    /**
     * @inheritDoc
     */
    public function isCached(): bool
    {
        return false;
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
        $this->cachePoolItem->set($filenames);
        $this->cachePool->saveDeferred($this->cachePoolItem);

        $this->currentIndex = -1;
    }
}
