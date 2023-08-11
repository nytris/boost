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

namespace Nytris\Boost\FsCache\Stream\Handler;

use Asmblah\PhpCodeShift\Shifter\Stream\Handler\AbstractStreamHandlerDecorator;
use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Asmblah\PhpCodeShift\Shifter\Stream\Native\StreamWrapperInterface;
use Nytris\Boost\FsCache\FsCacheInterface;
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
    private array $realpathCache = [];
    private ?CacheItemInterface $realpathCachePoolItem = null;
    private array $statCache = [];
    private ?CacheItemInterface $statCachePoolItem = null;

    public function __construct(
        StreamHandlerInterface $wrappedStreamHandler,
        private readonly ?CacheItemPoolInterface $realpathCachePool,
        private readonly ?CacheItemPoolInterface $statCachePool,
        string $realpathCacheKey = FsCacheInterface::DEFAULT_REALPATH_CACHE_KEY,
        string $statCacheKey = FsCacheInterface::DEFAULT_STAT_CACHE_KEY
    ) {
        parent::__construct($wrappedStreamHandler);

        // Load the realpath and stat caches from the PSR caches if enabled.
        if ($this->realpathCachePool !== null) {
            $this->realpathCachePoolItem = $this->realpathCachePool->getItem($realpathCacheKey);

            if ($this->realpathCachePoolItem->isHit()) {
                $this->realpathCache = $this->realpathCachePoolItem->get();
            }
        }

        if ($this->statCachePool !== null) {
            $this->statCachePoolItem = $this->statCachePool->getItem($statCacheKey);

            if ($this->statCachePoolItem->isHit()) {
                $this->statCache = $this->statCachePoolItem->get();
            }
        }
    }

    /**
     * To reduce I/O, defer PSR cache persistence (if enabled) until the stream handler is disposed of
     * (usually at the end of the request or CLI process).
     */
    public function __destruct()
    {
        $this->persistRealpathCache();
        $this->persistStatCache();
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
    }

    /**
     * Clears both the realpath and stat caches for the given path.
     */
    public function invalidatePath(string $path): void
    {
        $realpath = $this->getRealpath($path) ?? $path;

        unset($this->realpathCache[$realpath]);
        unset($this->statCache[$realpath]);
    }

    /**
     * @inheritDoc
     */
    public function mkdir(StreamWrapperInterface $streamWrapper, string $path, int $mode, int $options): bool
    {
        $this->invalidatePath($path);

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
        $this->realpathCachePool->saveDeferred($this->realpathCachePoolItem);
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
        $this->statCachePool->saveDeferred($this->statCachePoolItem);
    }

    /**
     * @inheritDoc
     */
    public function rename(StreamWrapperInterface $streamWrapper, string $fromPath, string $toPath): bool
    {
        $this->invalidatePath($fromPath);
        $this->invalidatePath($toPath);

        return $this->wrappedStreamHandler->rename($streamWrapper, $fromPath, $toPath);
    }

    /**
     * @inheritDoc
     */
    public function rmdir(StreamWrapperInterface $streamWrapper, string $path, int $options): bool
    {
        $this->invalidatePath($path);

        return $this->wrappedStreamHandler->rmdir($streamWrapper, $path, $options);
    }

    /**
     * @inheritDoc
     */
    public function streamMetadata(string $path, int $option, mixed $value): bool
    {
        $this->invalidatePath($path);

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
        $isRead = str_contains($mode, 'r') && !str_contains($mode, '+');

        if ($isRead) {
            $effectivePath = $this->getRealpath($path);

            if ($effectivePath === null) {
                return null;
            }
        } else {
            $effectivePath = $path;

            // File is being written to, so clear cache (e.g. in case it was cached as non-existent).
            $this->invalidatePath($effectivePath);
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

        return $stat;
    }

    /**
     * @inheritDoc
     */
    public function unlink(StreamWrapperInterface $streamWrapper, string $path): bool
    {
        $this->invalidatePath($path);

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

        return $stat;
    }
}
