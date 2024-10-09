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

namespace Nytris\Boost\FsCache\Realpath;

use Asmblah\PhpCodeShift\Shifter\Stream\Handler\StreamHandlerInterface;
use Nytris\Boost\FsCache\CanonicaliserInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class RealpathCache.
 *
 * Caches realpaths, optionally also to a PSR cache implementation, to improve performance.
 *
 * @phpstan-import-type RealpathCacheEntry from RealpathCacheInterface
 * @phpstan-import-type RealpathCacheStorage from RealpathCacheInterface
 * @author Dan Phillimore <dan@ovms.co>
 */
class RealpathCache implements RealpathCacheInterface
{
    /**
     * Realpaths may be resolved multiple times, e.g. to resolve an eventual path
     * but then to attempt to resolve to a realpath to determine file existence.
     * This volume may cause a lot of load on the PSR backing store/in general
     * even if in-memory, with many CacheItem instances being created on the heap etc.,
     * so we keep a simple write-through entry cache here.
     *
     * @var RealpathCacheStorage
     */
    private array $realpathEntryCache;

    public function __construct(
        private readonly StreamHandlerInterface $wrappedStreamHandler,
        private readonly CanonicaliserInterface $canonicaliser,
        ?CacheItemPoolInterface $realpathPreloadCachePool,
        private readonly CacheItemPoolInterface $realpathCachePool,
        /**
         * Whether the non-existence of files should be cached in the realpath cache.
         */
        private readonly bool $cacheNonExistentFiles,
        private readonly bool $asVirtualFilesystem
    ) {
        if ($realpathPreloadCachePool) {
            $item = $realpathPreloadCachePool->getItem(self::PRELOAD_CACHE_KEY);

            $this->realpathEntryCache = $item->isHit() ? $item->get() : [];
        } else {
            $this->realpathEntryCache = [];
        }
    }

    /**
     * @inheritDoc
     */
    public function cacheRealpath(string $canonicalPath, string $realpath): void
    {
        $this->setBackingCacheEntry($realpath, [
            'realpath' => $realpath,
        ]);

        if ($canonicalPath !== $realpath) {
            // Canonical path is not the same as the realpath, e.g. path is a symlink.
            // Add pointer entry from canonical targeting the final symlink one.
            $this->setBackingCacheEntry($canonicalPath, [
                'symlink' => $realpath,
            ]);
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteBackingCacheEntry(string $realpath): void
    {
        $this->realpathCachePool->deleteItem($this->canonicaliser->canonicaliseCacheKey($realpath));
        unset($this->realpathEntryCache[$realpath]);
    }

    /**
     * @inheritDoc
     */
    public function getBackingCacheEntry(string $realpath): ?array
    {
        $entry = $this->realpathEntryCache[$realpath] ?? null;

        if ($entry !== null) {
            return $entry;
        }

        $realpathCacheItem = $this->realpathCachePool->getItem(
            $this->canonicaliser->canonicaliseCacheKey($realpath)
        );

        return $realpathCacheItem->isHit() ? $realpathCacheItem->get() : null;
    }

    /**
     * @inheritDoc
     */
    public function getCachedEventualPath(
        string $path,
        bool $followSymlinks = true,
        bool &$accessible = true
    ): string {
        $entry = $this->getBackingCacheEntry($path);

        if ($entry === null) {
            return $path;
        }

        $canonicalPath = $entry['canonical'] ?? null;

        if ($canonicalPath !== null) {
            $entry = $this->getBackingCacheEntry($canonicalPath);

            if ($entry === null) {
                return $canonicalPath;
            }
        }

        if ($followSymlinks) {
            $symlinkPath = $entry['symlink'] ?? null;

            if ($symlinkPath !== null) {
                $entry = $this->getBackingCacheEntry($symlinkPath);

                if ($entry === null) {
                    return $symlinkPath;
                }
            }
        } else {
            $symlinkPath = null;
        }

        $accessible = $entry['exists'] ?? true;

        return $entry['realpath'] ?? $canonicalPath ?? $symlinkPath ?? $path;
    }

    /**
     * @inheritDoc
     */
    public function getEventualPath(
        string $path,
        bool $followSymlinks = true,
        bool &$accessible = true
    ): string {
        return $this->getRealpath(
            $path,
            getEventual: true,
            followSymlinks: $followSymlinks,
            accessible: $accessible
        );
    }

    /**
     * @inheritDoc
     */
    public function getInMemoryEntryCache(): array
    {
        return $this->realpathEntryCache;
    }

    /**
     * @inheritDoc
     */
    public function getRealpath(
        string $path,
        bool $getEventual = false,
        bool $followSymlinks = true,
        bool &$accessible = true
    ): ?string {
        $entry = $this->getRealpathCacheEntry($path, followSymlinks: $followSymlinks);

        if ($entry !== null) {
            $exists = $entry['exists'] ?? true;

            if (!$exists) {
                // File has been cached as non-existent.
                $accessible = false;

                return $getEventual ? $path : null;
            }

            $realpath = $entry['realpath'] ?? $entry['symlink'];
        } else {
            $realpath = null;
        }

        if ($realpath !== null) {
            $accessible = true;

            return $realpath;
        }

        $canonicalPath = $this->canonicaliser->canonicalise($path);

        if ($path !== $canonicalPath) {
            // Path is not canonical, add pointer entry targeting the canonical one.
            $this->setBackingCacheEntry($path, [
                'canonical' => $canonicalPath,
            ]);
        }

        if ($this->asVirtualFilesystem || !$followSymlinks) {
            // We cannot hit the backing store in order to resolve a true realpath -
            // just use the canonical one.
            $realpath = $canonicalPath;
        } else {
            $realpath = realpath($path);
        }

        if ($realpath === false) {
            if ($this->wrappedStreamHandler->unwrapped(fn () => is_link($path))) {
                // File is a symlink to an inaccessible target file.
                $symlinkTarget = readlink($path);

                if ($symlinkTarget !== false) {
                    $canonicalSymlinkTarget = $this->canonicaliser->canonicalise($symlinkTarget);

                    $this->setBackingCacheEntry($canonicalPath, [
                        'symlink' => $canonicalSymlinkTarget,
                    ]);

                    if ($this->cacheNonExistentFiles) {
                        $this->setBackingCacheEntry($canonicalSymlinkTarget, [
                            'exists' => false,
                        ]);
                    }

                    // File does not exist or is inaccessible.
                    $accessible = false;

                    return $getEventual ? $canonicalSymlinkTarget : null;
                }
            }

            if ($this->cacheNonExistentFiles) {
                // Add canonical entry.
                $this->setBackingCacheEntry($canonicalPath, [
                    'exists' => false,
                ]);
            }

            // File does not exist or is inaccessible.
            $accessible = false;

            return $getEventual ? $canonicalPath : null;
        }

        $this->cacheRealpath($canonicalPath, $realpath);

        $accessible = true;

        return $realpath;
    }

    /**
     * @inheritDoc
     */
    public function getRealpathCacheEntry(string $path, bool $followSymlinks): ?array
    {
        $entry = $this->getBackingCacheEntry($path);

        if ($entry === null) {
            // TODO: Clear pointer entry from cache?

            return null; // Not in cache; early-out.
        }

        $canonicalPath = $entry['canonical'] ?? null;

        if ($canonicalPath !== null) {
            $entry = $this->getBackingCacheEntry($canonicalPath);

            if ($entry === null) {
                // TODO: Clear pointer entry from cache?

                return null; // Not in cache; early-out.
            }
        }

        if ($followSymlinks) {
            $symlinkPath = $entry['symlink'] ?? null;

            if ($symlinkPath !== null) {
                $entry = $this->getBackingCacheEntry($symlinkPath);

                if ($entry === null) {
                    // TODO: Clear pointer entry from cache?

                    return null; // Not in cache; early-out.
                }
            }
        }

        return $entry;
    }

    /**
     * @inheritDoc
     */
    public function invalidate(): void
    {
        $this->realpathCachePool->clear();

        $this->realpathEntryCache = [];
    }

    /**
     * @inheritDoc
     */
    public function invalidatePath(string $path): void
    {
        // Resolve this first, as it may need the canonical path that is cleared below.
        $eventualPath = $this->getCachedEventualPath($path);

        // Clear the canonical path entries (which may be pointed to by some symbolic path entries -
        // this action will effectively invalidate those too).
        $canonicalPath = $this->canonicaliser->canonicalise($path);
        $this->deleteBackingCacheEntry($canonicalPath);

        // Clear the eventual path entries if not cleared above.
        $this->deleteBackingCacheEntry($eventualPath);
    }

    /**
     * @inheritDoc
     */
    public function persistRealpathCache(): void
    {
        $this->realpathCachePool->commit();
    }

    /**
     * @inheritDoc
     */
    public function setBackingCacheEntry(string $realpath, array $entry): void
    {
        $realpathCacheItem = $this->realpathCachePool->getItem(
            $this->canonicaliser->canonicaliseCacheKey($realpath)
        );

        $realpathCacheItem->set($entry);
        $this->realpathCachePool->saveDeferred($realpathCacheItem);

        $this->realpathEntryCache[$realpath] = $entry;
    }

    /**
     * @inheritDoc
     */
    public function setInMemoryCacheEntry(string $realpath, array $entry): void
    {
        $this->realpathEntryCache[$realpath] = $entry;
    }
}
