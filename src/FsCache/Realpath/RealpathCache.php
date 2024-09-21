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
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class RealpathCache.
 *
 * Caches realpaths, optionally also to a PSR cache implementation, to improve performance.
 *
 * @phpstan-import-type RealpathCacheStorage from RealpathCacheInterface
 * @phpstan-import-type RealpathCacheEntry from RealpathCacheInterface
 * @author Dan Phillimore <dan@ovms.co>
 */
class RealpathCache implements RealpathCacheInterface
{
    /**
     * @var RealpathCacheStorage
     */
    private array $realpathCache = [];
    private bool $realpathCacheIsDirty = false;
    private ?CacheItemInterface $realpathCachePoolItem = null;

    public function __construct(
        private readonly StreamHandlerInterface $wrappedStreamHandler,
        private readonly CanonicaliserInterface $canonicaliser,
        private readonly ?CacheItemPoolInterface $realpathCachePool,
        string $realpathCacheKey,
        /**
         * Whether the non-existence of files should be cached in the realpath cache.
         */
        private readonly bool $cacheNonExistentFiles,
        private readonly bool $asVirtualFilesystem
    ) {
        // Load the realpath cache from the PSR cache if enabled.
        if ($this->realpathCachePool !== null) {
            $this->realpathCachePoolItem = $this->realpathCachePool->getItem($realpathCacheKey);

            if ($this->realpathCachePoolItem->isHit()) {
                $this->realpathCache = $this->realpathCachePoolItem->get();
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function cacheRealpath(string $canonicalPath, string $realpath): void
    {
        $this->realpathCache[$realpath] = [
            'realpath' => $realpath,
        ];

        if ($canonicalPath !== $realpath) {
            // Canonical path is not the same as the realpath, e.g. path is a symlink.
            // Add pointer entry from canonical targeting the final symlink one.
            $this->realpathCache[$canonicalPath] = [
                'symlink' => $realpath,
            ];
        }

        $this->realpathCacheIsDirty = true;
    }

    /**
     * @inheritDoc
     */
    public function getCachedEventualPath(
        string $path,
        bool $followSymlinks = true,
        bool &$accessible = true
    ): string {
        $entry = $this->realpathCache[$path] ?? null;

        if ($entry === null) {
            return $path;
        }

        $canonicalPath = $entry['canonical'] ?? null;

        if ($canonicalPath !== null) {
            $entry = $this->realpathCache[$canonicalPath] ?? null;

            if ($entry === null) {
                return $canonicalPath;
            }
        }

        if ($followSymlinks) {
            $symlinkPath = $entry['symlink'] ?? null;

            if ($symlinkPath !== null) {
                $entry = $this->realpathCache[$symlinkPath] ?? null;

                if ($entry === null) {
                    return $symlinkPath;
                }
            }
        } else {
            $symlinkPath = null;
        }

        if (($entry['exists'] ?? true) === false) {
            $accessible = false;
        }

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
            return $realpath;
        }

        $canonicalPath = $this->canonicaliser->canonicalise($path);

        if ($path !== $canonicalPath) {
            // Path is not canonical, add pointer entry targeting the canonical one.
            $this->realpathCache[$path] = [
                'canonical' => $canonicalPath,
            ];

            $this->realpathCacheIsDirty = true;
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

                    $this->realpathCache[$canonicalPath] = [
                        'symlink' => $canonicalSymlinkTarget,
                    ];

                    if ($this->cacheNonExistentFiles) {
                        $this->realpathCache[$canonicalSymlinkTarget] = [
                            'exists' => false,
                        ];
                    }

                    $this->realpathCacheIsDirty = true;

                    // File does not exist or is inaccessible.
                    return $getEventual ? $canonicalSymlinkTarget : null;
                }
            }

            if ($this->cacheNonExistentFiles) {
                // Add canonical entry.
                $this->realpathCache[$canonicalPath] = [
                    'exists' => false,
                ];
            }

            $this->realpathCacheIsDirty = true;

            // File does not exist or is inaccessible.
            $accessible = false;

            return $getEventual ? $canonicalPath : null;
        }

        $this->cacheRealpath($canonicalPath, $realpath);

        return $realpath;
    }

    /**
     * @inheritDoc
     */
    public function getRealpathCacheEntry(string $path, bool $followSymlinks): ?array
    {
        $entry = $this->realpathCache[$path] ?? null;

        if ($entry === null) {
            // TODO: Clear pointer entry from cache?

            return null; // Not in cache; early-out.
        }

        $canonicalPath = $entry['canonical'] ?? null;

        if ($canonicalPath !== null) {
            $entry = $this->realpathCache[$canonicalPath] ?? null;

            if ($entry === null) {
                // TODO: Clear pointer entry from cache?

                return null; // Not in cache; early-out.
            }
        }

        if ($followSymlinks) {
            $symlinkPath = $entry['symlink'] ?? null;

            if ($symlinkPath !== null) {
                $entry = $this->realpathCache[$symlinkPath] ?? null;

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
        $this->realpathCache = [];

        $this->realpathCacheIsDirty = true;
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
        unset($this->realpathCache[$canonicalPath]);

        // Clear the eventual path entries if not cleared above.
        unset($this->realpathCache[$eventualPath]);

        $this->realpathCacheIsDirty = true;
    }

    /**
     * @inheritDoc
     */
    public function persistRealpathCache(): void
    {
        if ($this->realpathCachePoolItem === null || $this->realpathCacheIsDirty === false) {
            return; // Persistence is disabled or nothing changed; nothing to do.
        }

        $this->realpathCachePoolItem->set($this->realpathCache);
        $this->realpathCachePool->saveDeferred($this->realpathCachePoolItem);
    }
}
