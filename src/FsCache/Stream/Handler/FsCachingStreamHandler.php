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
use Asmblah\PhpCodeShift\Util\CallStackInterface;
use Nytris\Boost\FsCache\CanonicaliserInterface;
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
class FsCachingStreamHandler extends AbstractStreamHandlerDecorator implements FsCachingStreamHandlerInterface
{
    private array $realpathCache = [];
    private bool $realpathCacheIsDirty = false;
    private ?CacheItemInterface $realpathCachePoolItem = null;
    private array $statCache = [];
    private bool $statCacheIsDirty = false;
    private ?CacheItemInterface $statCachePoolItem = null;

    public function __construct(
        StreamHandlerInterface $wrappedStreamHandler,
        private readonly CanonicaliserInterface $canonicaliser,
        private readonly CallStackInterface $callStack,
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
    public function getEventualPath(string $path): string
    {
        $canonicalPath = $this->canonicaliser->canonicalise($path, getcwd());

        $entry = $this->realpathCache[$canonicalPath] ?? null;
        $realpath = $entry['realpath'] ?? null;

        if ($realpath !== null) {
            return $realpath;
        }

        // Follow symlink if it is one, as realpath will return false if eventual path is inaccessible.
        if ($this->unwrapped(fn () => is_link($canonicalPath))) {
            $resolvedPath = readlink($canonicalPath);

            if ($resolvedPath !== false) {
                // Link target may not be canonical, so we must canonicalise again.
                $resolvedPath = $this->canonicaliser->canonicalise($resolvedPath, getcwd());
            } else {
                $resolvedPath = $canonicalPath;
            }
        } else {
            $resolvedPath = $canonicalPath;
        }

        $realpath = realpath($resolvedPath);

        return $realpath !== false ? $realpath : $resolvedPath;
    }

    /**
     * @inheritDoc
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
            $canonicalPath = $this->canonicaliser->canonicalise($path, getcwd());

            if ($path !== $canonicalPath) {
                // Path is not canonical, add pointer entry targeting the canonical one.
                $this->realpathCache[$path] = [
                    'canonical' => $canonicalPath,
                ];
            }

            $realpath = realpath($path);

            if ($realpath === false) {
                if ($this->unwrapped(fn () => is_link($path))) {
                    // File is a symlink to an inaccessible target file.
                    $symlinkTarget = readlink($path);

                    if ($symlinkTarget !== false) {
                        $canonicalSymlinkTarget = $this->canonicaliser->canonicalise($symlinkTarget, getcwd());

                        $this->realpathCache[$canonicalPath] = [
                            'symlink' => $canonicalSymlinkTarget,
                        ];

                        $this->realpathCache[$canonicalSymlinkTarget] = [
                            'exists' => false,
                        ];

                        $this->realpathCacheIsDirty = true;

                        return null; // File does not exist or is inaccessible.
                    }
                }

                // Add canonical entry.
                $this->realpathCache[$canonicalPath] = [
                    'exists' => false,
                ];

                $this->realpathCacheIsDirty = true;

                return null; // File does not exist or is inaccessible.
            }

            $this->cacheRealpath($canonicalPath, $realpath);
        }

        return $realpath;
    }

    /**
     * @inheritDoc
     */
    public function getRealpathCacheEntry(string $path): ?array
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

        $symlinkPath = $entry['symlink'] ?? null;

        if ($symlinkPath !== null) {
            $entry = $this->realpathCache[$symlinkPath] ?? null;

            if ($entry === null) {
                // TODO: Clear pointer entry from cache?

                return null; // Not in cache; early-out.
            }
        }

        return $entry;
    }

    /**
     * @inheritDoc
     */
    public function invalidateCaches(): void
    {
        $this->realpathCache = [];
        $this->statCache = [];
    }

    /**
     * @inheritDoc
     */
    public function invalidatePath(string $path): void
    {
        // Clear the canonical path entries (which may be pointed to by some symbolic path entries -
        // this action will effectively invalidate those too).
        $canonicalPath = $this->canonicaliser->canonicalise($path, getcwd());

        unset($this->realpathCache[$canonicalPath]);
        unset($this->statCache[$canonicalPath]);

        // Clear the eventual path entries if not cleared above.
        $eventualPath = $this->getEventualPath($path);

        unset($this->realpathCache[$eventualPath]);
        unset($this->statCache[$eventualPath]);

        $this->realpathCacheIsDirty = true;
        $this->statCacheIsDirty = true;
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

    /**
     * @inheritDoc
     */
    public function persistStatCache(): void
    {
        if ($this->statCachePoolItem === null || $this->statCacheIsDirty === false) {
            return; // Persistence is disabled or nothing changed; nothing to do.
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
        $this->statCacheIsDirty = true;

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
        $isLinkStat = $flags & STREAM_URL_STAT_LINK;

        if ($isLinkStat) {
            // Link status fetches (lstat()s) stat the symlink file itself (if one exists at the given path)
            // vs. stat() which stats the eventual file that the symlink points to.
            $canonicalPath = $this->canonicaliser->canonicalise($path, getcwd());

            $entry = $this->realpathCache[$canonicalPath] ?? null;

            if ($entry !== null) {
                $exists = $entry['exists'] ?? true;

                if (!$exists) {
                    return false; // File has been cached as non-existent.
                }

                $linkPath = $entry['symlink'] ?? $entry['realpath'];
            } else {
                $linkPath = $canonicalPath;
            }
        } else {
            $linkPath = $this->getRealpath($path);

            if ($linkPath === null) {
                return false; // File has been cached as non-existent or is inaccessible.
            }
        }

        if (array_key_exists($linkPath, $this->statCache)) {
            // Stat is already cached: just return it.
            return $this->statCache[$linkPath];
        }

        $stat = $this->wrappedStreamHandler->urlStat($path, $flags);

        if ($stat === false) {
            // Stat failed.
            return false;
        }

        /*
         * When using the native stream wrapper, is_writable(...) correctly handles ACLs.
         * However, when using a custom stream wrapper, only the mode in the returned stat is checked.
         * This means that if write permission is only granted by ACL for example, then as that cannot
         * be represented within the mode, is_writable(...) ends up returning false.
         *
         * We cannot directly change the return value of is_writable(...), but we can tweak the mode
         * in the returned stat to allow write permission.
         *
         * Similar to the native stream wrapper, we determine which of the Unix permission classes
         * (user, group or other) is most applicable and set its relevant bit,
         * because PHP will internally check the relevant one based on ownership of the file.
         *
         * Note that due to PHP's stat cache, if this file is stat'ed again before a different file,
         * the modified stat result (with tweaked Unix permissions mode) will be used.
         *
         * TODO: Handle ACLs for is_readable(...) and is_executable(...) in the same way.
         */
        $isWritable = $this->unwrapped(fn () => is_writable($path));

        if ($isWritable) {
            // As explained above, tweak the mode accordingly to emulate the ACL within the Unix permission mode.
            $bitmask = 0;

            if (extension_loaded('posix')) {
                if ($stat['uid'] === posix_getuid()) {
                    $bitmask = 0200;
                } elseif ($stat['gid'] === posix_getgid()) {
                    $bitmask = 0020;
                } else {
                    foreach (posix_getgroups() as $groupId) {
                        if ($stat['gid'] === $groupId) {
                            $bitmask = 0020;
                            break;
                        }
                    }
                }
            }

            if ($bitmask === 0) {
                // Use the "other" permission class otherwise.
                $bitmask = 0002;
            }

            $stat['mode'] |= $bitmask;
        }

        // If not writable, then there is no reason to tweak the Unix permission mode.

        // Cache stat for future reference.
        $this->statCache[$linkPath] = $stat;
        $this->statCacheIsDirty = true;

        return $stat;
    }
}
