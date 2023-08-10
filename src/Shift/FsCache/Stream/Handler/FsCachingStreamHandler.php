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
use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapperInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class FsCachingStreamHandler.
 *
 * Caches realpath and filesystem stats, optionally also to a PSR cache implementation,
 * to improve performance.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class FsCachingStreamHandler extends AbstractStreamHandlerDecorator
{
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

        // Load the realpath and stat caches from the PSR cache.
        $this->realpathCachePoolItem = $this->cachePool->getItem($this->cachePrefix . 'realpath_cache');
        $this->statCachePoolItem = $this->cachePool->getItem($this->cachePrefix . 'stat_cache');

        if ($this->realpathCachePoolItem->isHit()) {
            $this->realpathCache = $this->realpathCachePoolItem->get();
        } else {
            $this->realpathCache = [];

            $this->persistRealpathCache();
        }

        if ($this->statCachePoolItem->isHit()) {
            $this->statCache = $this->statCachePoolItem->get();
        } else {
            $this->statCache = [];

            $this->persistStatCache();
        }
    }

    /**
     * Caches the fact that a path does not exist in the realpath cache,
     * to optimise future lookups for the same path.
     */
    public function cacheNonExistentPath(string $path): void
    {
        $this->realpathCache[$path] = [
            'exists' => false,
        ];

        $this->persistRealpathCache();
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
            'is_dir' => $isDirectory,
            'realpath' => $realPath,
            'expires' => 0, // FIXME.
        ];
    }

    /**
     * Fetches the realpath for the given path if cached and not expired,
     * otherwise resolves and caches it.
     */
    public function getRealpath(string $path): ?string
    {
        $entry = $this->getRealpathCacheEntry($path);

        if ($entry !== null) {
            $exists = $entry['exists'] ?? true;

            if (!$exists) {
                return null; // File has been cached as non-existent.
            }

            $realpath = $entry['realpath'];
        } else {
            $realpath = null;
        }

        if ($realpath === null) {
            $realpath = realpath($path);

            if ($realpath === false) {
                $this->cacheNonExistentPath($path);

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
     * Clears both the realpath and stat caches.
     */
    public function invalidateCaches(): void
    {
        $this->realpathCache = [];
        $this->statCache = [];

        $this->persistRealpathCache();
        $this->persistStatCache();
    }

    /**
     * @inheritDoc
     */
    public function mkdir(StreamWrapperInterface $streamWrapper, string $path, int $mode, int $options): bool
    {
        // TODO?

        return $this->wrappedStreamHandler->mkdir($streamWrapper, $path, $mode, $options);
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
        $this->cachePool->saveDeferred($this->realpathCachePoolItem);
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
        $this->cachePool->saveDeferred($this->statCachePoolItem);
    }

    /**
     * @inheritDoc
     */
    public function rename(StreamWrapperInterface $streamWrapper, string $fromPath, string $toPath): bool
    {
        // TODO?

        return $this->wrappedStreamHandler->rename($streamWrapper, $fromPath, $toPath);
    }

    /**
     * @inheritDoc
     */
    public function rmdir(StreamWrapperInterface $streamWrapper, string $path, int $options): bool
    {
        // TODO?

        return $this->wrappedStreamHandler->rmdir($streamWrapper, $path, $options);
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
        StreamWrapperInterface $streamWrapper,
        string $path,
        string $mode,
        int $options,
        ?string &$openedPath
    ) {
        // TODO?
        $effectivePath = $this->getRealpath($path) ?? null;

        if ($effectivePath === null) {
            return null;
        }

        return $this->wrappedStreamHandler->streamOpen($streamWrapper, $effectivePath, $mode, $options, $openedPath);
    }

    /**
     * @inheritDoc
     */
    public function streamStat(StreamWrapperInterface $streamWrapper): array|false
    {
        // TODO?
        $path = $streamWrapper->getOpenPath();
        $realpath = $this->getRealpath($path);

        if ($realpath === null) {
            return false;
        }

        if (array_key_exists($realpath, $this->statCache)) {
            // Stat is already cached: just return it.
            return $this->statCache[$realpath];
        }

        $stat = $this->wrappedStreamHandler->streamStat($streamWrapper);

        if ($stat === false) {
            // Stat failed.
            return false;
        }

        // Cache stat for future reference.
        $this->statCache[$realpath] = $stat;
        $this->persistStatCache();

        return $stat;
    }

    /**
     * @inheritDoc
     */
    public function unlink(StreamWrapperInterface $streamWrapper, string $path): bool
    {
        // TODO.

        return $this->wrappedStreamHandler->unlink($streamWrapper, $path);
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
