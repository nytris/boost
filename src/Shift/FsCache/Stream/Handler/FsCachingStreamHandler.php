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

namespace Nytris\Boost\Shift\FsCache\Stream\Handler;

use Asmblah\PhpCodeShift\Shifter\Stream\Handler\AbstractStreamHandlerDecorator;
use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class FsCachingStreamHandler.
 *
 * TODO: Allow realpath/stat caches to themselves be persisted to disk, so future requests
 *       including those from other processes can benefit.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class FsCachingStreamHandler extends AbstractStreamHandlerDecorator
{
    private int $nextKey = 0;
    private array $realpathCache;
    private ?CacheItemInterface $realpathCachePoolItem;
    private ?CacheItemInterface $statCachePoolItem;
    private array $statCache;

    public function __construct(
        StreamHandlerInterface $wrappedStreamHandler,
        private readonly ?CacheItemPoolInterface $cachePool,
        private readonly string $cachePrefix
    ) {
        parent::__construct($wrappedStreamHandler);

        if ($this->cachePool === null) {
            // PSR cache persistence is disabled.
            $this->realpathCache = [];
            $this->statCache = [];

            return;
        }

        $this->realpathCachePoolItem = $this->cachePool->getItem($this->cachePrefix . 'realpath_cache');
        $this->statCachePoolItem = $this->cachePool->getItem($this->cachePrefix . 'stat_cache');

        if ($this->realpathCachePoolItem->isHit()) {
            $this->realpathCache = $this->realpathCachePoolItem->get();
        } else {
            $this->realpathCache = [];
            $this->realpathCachePoolItem->set([]);
        }

        if ($this->statCachePoolItem->isHit()) {
            $this->statCache = $this->statCachePoolItem->get();
        } else {
            $this->statCache = [];
            $this->statCachePoolItem->set([]);
        }
    }

    /**
     * Adds the given path (and all segments of the realpath) to the realpath cache.
     */
    public function cacheRealpath(string $path, string $realpath, bool $isDirectory): void
    {
        $pathToSegment = '';
        $segments = explode('/', ltrim($realpath, '/'));
        $segmentCount = count($segments);

        foreach ($segments as $segmentIndex => $segment) {
            $pathToSegment .= '/' . $segment;

            $this->cacheRealpathSegment($pathToSegment, $pathToSegment, $isDirectory || $segmentIndex < $segmentCount - 1);
        }

        $this->cacheRealpathSegment($path, $realpath, $isDirectory);

        $this->persistRealpathCache();
    }

    /**
     * Adds the given path, which may be to a single segment of a given path, to the realpath cache.
     */
    public function cacheRealpathSegment(string $path, string $realPath, bool $isDirectory): void
    {
        $this->realpathCache[$path] = [
            'key' => $this->nextKey++,
            'is_dir' => $isDirectory,
            'realpath' => $realPath,
            'expires' => 0, // FIXME.
        ];
    }

    public function clearCaches(): void
    {
        $this->realpathCache = [];
        $this->statCache = [];

        $this->persistRealpathCache();
        $this->persistStatCache();
    }

    /**
     * Fetches the realpath for the given path if cached and not expired,
     * otherwise resolves and caches it.
     */
    public function getRealpath(string $path): ?string
    {
        $entry = $this->getRealpathCacheEntry($path);

        $realpath = $entry !== null ? $entry['realpath'] : null;

        if ($realpath === null) {
            $realpath = realpath($path);

            if ($realpath === false) {
                return null; // File does not exist or is inaccessible.
            }

            $isDirectory = $this->unwrapped(fn () => is_dir($realpath));

            $this->cacheRealpath($path, $realpath, $isDirectory);
        }

        return $realpath;
    }

    /**
     * Fetches the realpath cache entry for the given path if cached and not expired,
     * or null otherwise.
     */
    public function getRealpathCacheEntry(string $path): ?array
    {
        if (!array_key_exists($path, $this->realpathCache)) {
            return null; // Not in cache; early-out.
        }

        $entry = $this->realpathCache[$path];

        // TODO: Expire if entry has expired.

        return $entry;
    }

    /**
     * @inheritDoc
     */
    public function mkdir($context, string $path, int $mode, int $options): bool
    {
        // TODO?

        return $this->wrappedStreamHandler->mkdir($context, $path, $mode, $options);
    }

    /**
     * Persists the current realpath cache via configured PSR cache.
     */
    public function persistRealpathCache(): void
    {
        if ($this->realpathCachePoolItem === null) {
            return; // Persistence is disabled; nothing to do.
        }

        $this->realpathCachePoolItem->set($this->realpathCache);
    }

    /**
     * Persists the current stat cache via configured PSR cache.
     */
    public function persistStatCache(): void
    {
        if ($this->statCachePoolItem === null) {
            return; // Persistence is disabled; nothing to do.
        }

        $this->statCachePoolItem->set($this->statCache);
    }

    /**
     * @inheritDoc
     */
    public function rename($context, string $fromPath, string $toPath): bool
    {
        // TODO?

        return $this->wrappedStreamHandler->rename($context, $fromPath, $toPath);
    }

    /**
     * @inheritDoc
     */
    public function rmdir($context, string $path, int $options): bool
    {
        // TODO?

        return $this->wrappedStreamHandler->rmdir($context, $path, $options);
    }

    /**
     * @inheritDoc
     */
    public function streamMetadata(string $path, int $option, mixed $value): bool
    {
        // TODO?

        return $this->wrappedStreamHandler->streamMetadata($path, $option, $value);
    }

    /**
     * @inheritDoc
     */
    public function streamOpen(
        $context,
        string $path,
        string $mode,
        int $options,
        ?string &$openedPath
    ) {
        // TODO?
        $realpath = $this->getRealpath($path);

        return $this->wrappedStreamHandler->streamOpen($context, $realpath, $mode, $options, $openedPath);
    }

    /**
     * @inheritDoc
     */
    public function streamStat($wrappedResource): array|false
    {
        // TODO?

        return $this->wrappedStreamHandler->streamStat($wrappedResource);
    }

    /**
     * @inheritDoc
     */
    public function unlink($context, string $path): bool
    {
        // TODO.

        return $this->wrappedStreamHandler->unlink($context, $path);
    }

    /**
     * @inheritDoc
     */
    public function urlStat(string $path, int $flags): array|false
    {
        // Use lstat(...) for links but stat() for other files.
        // FIXME: Cache these differently?
        $isLinkStat = $flags & STREAM_URL_STAT_LINK;

        $realpath = $this->getRealpath($path);

        if ($realpath === null) {
            return false;
        }

        if (array_key_exists($realpath, $this->statCache)) {
            // Stat is already cached: just return it.
            return $this->statCache[$realpath];
        }

        $stat = $this->wrappedStreamHandler->urlStat($path, $flags);

        if ($stat === false) {
            // Stat failed.
            return false;
        }

        // Cache stat for future reference.
        $this->statCache[$realpath] = $stat;
        $this->persistStatCache();

        return $stat;
    }
}
