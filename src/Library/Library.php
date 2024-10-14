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

namespace Nytris\Boost\Library;

use Asmblah\PhpCodeShift\CodeShift;
use Asmblah\PhpCodeShift\CodeShiftInterface;
use Asmblah\PhpCodeShift\Shifter\Filter\FileFilter;
use Asmblah\PhpCodeShift\Shifter\Filter\FileFilterInterface;
use Asmblah\PhpCodeShift\Shifter\Shift\Shift\FunctionHook\FunctionHookShiftSpec;
use Nytris\Boost\BoostInterface;
use Nytris\Boost\Environment\Environment;
use Nytris\Boost\Environment\EnvironmentInterface;
use Nytris\Boost\FsCache\Canonicaliser;
use Nytris\Boost\FsCache\CanonicaliserInterface;
use Nytris\Boost\FsCache\Stat\StatCacheInterface;

/**
 * Class Library.
 *
 * Encapsulates an installation of the library.
 *
 * @phpstan-import-type StatCacheStorage from StatCacheInterface
 * @author Dan Phillimore <dan@ovms.co>
 */
class Library implements LibraryInterface
{
    /**
     * @var BoostInterface[]
     */
    private array $boosts = [];
    private readonly CanonicaliserInterface $canonicaliser;

    public function __construct(
        private readonly EnvironmentInterface $environment = new Environment(),
        ?CanonicaliserInterface $canonicaliser = null,
        private readonly CodeShiftInterface $codeShift = new CodeShift()
    ) {
        $this->canonicaliser = $canonicaliser ?? new Canonicaliser($environment);

        // Exclude Boost's own source from being hooked to prevent a catch-22.
        $this->codeShift->deny(new FileFilter(dirname(__DIR__) . '/**'));
    }

    /**
     * @inheritDoc
     */
    public function addBoost(BoostInterface $boost): void
    {
        array_unshift($this->boosts, $boost);

        if (count($this->boosts) === 1) {
            $this->codeShift->install();
        }
    }

    /**
     * @inheritDoc
     */
    public function getBoosts(): array
    {
        return $this->boosts;
    }

    /**
     * @inheritDoc
     */
    public function getCanonicaliser(): CanonicaliserInterface
    {
        return $this->canonicaliser;
    }

    /**
     * @inheritDoc
     */
    public function getEnvironment(): EnvironmentInterface
    {
        return $this->environment;
    }

    /**
     * @inheritDoc
     */
    public function getInMemoryRealpathEntryCache(): array
    {
        return array_reduce(
            $this->boosts,
            static fn (array $carry, BoostInterface $boost) =>
                array_merge($carry, $boost->getInMemoryRealpathEntryCache()),
            []
        );
    }

    /**
     * @inheritDoc
     */
    public function getInMemoryStatEntryCache(): array
    {
        /** @var StatCacheStorage $mergedIncludeStats */
        $mergedIncludeStats = [];
        /** @var StatCacheStorage $mergedNonIncludeStats */
        $mergedNonIncludeStats = [];

        foreach ($this->boosts as $boost) {
            $multipleCache = $boost->getInMemoryStatEntryCache();

            $mergedIncludeStats = array_merge($mergedIncludeStats, $multipleCache['include']);
            $mergedNonIncludeStats = array_merge($mergedNonIncludeStats, $multipleCache['non_include']);
        }

        return [
            'include' => $mergedIncludeStats,
            'non_include' => $mergedNonIncludeStats,
        ];
    }

    /**
     * @inheritDoc
     */
    public function hookBuiltinFunctions(FileFilterInterface $fileFilter): void
    {
        // Hook the `clearstatcache()` function and simply have it fully clear both caches
        // in all installed Boost instances for now.
        // TODO: Implement parameters.
        $this->codeShift->shift(
            new FunctionHookShiftSpec(
                'clearstatcache',
                fn () => function (): void {
                    foreach ($this->boosts as $boost) {
                        $boost->invalidateCaches();
                    }
                }
            ),
            $fileFilter
        );

        if (extension_loaded('Zend OPcache')) {
            $this->codeShift->shift(
                new FunctionHookShiftSpec(
                    'opcache_invalidate',
                    static fn (callable $originalInvalidate) =>
                        static function (string $filename, bool $force = false) use ($originalInvalidate): bool {
                            if (ini_get('opcache.enable') === false) {
                                return false;
                            }

                            // Work around an issue where OPcache refuses to invalidate files
                            // served from the `file://` scheme where they do not map to a real path.
                            $validateTimestamps = ini_set('opcache.validate_timestamps', true);
                            $revalidateFrequency = ini_set('opcache.revalidate_freq', 0);
                            $fileUpdateProtection = ini_set('opcache.file_update_protection', 200000);
                            $originalInvalidate($filename, $force);
                            // Due to the `file_update_protection` setting assigned above,
                            // this will not actually cause a recompile of the current contents.
                            opcache_compile_file($filename);
                            ini_set('opcache.file_update_protection', $fileUpdateProtection);
                            ini_set('opcache.validate_timestamps', $validateTimestamps);
                            ini_set('opcache.revalidate_freq', $revalidateFrequency);

                            return true;
                        }
                ),
                $fileFilter
            );
        }

        $this->codeShift->shift(
            new FunctionHookShiftSpec(
                'realpath',
                fn () => function (string $path): string|false {
                    foreach ($this->boosts as $boost) {
                        $realpath = $boost->getRealpath($path);

                        if ($realpath !== null) {
                            return $realpath;
                        }
                    }

                    return false;
                }
            ),
            $fileFilter
        );
    }

    /**
     * @inheritDoc
     */
    public function removeBoost(BoostInterface $boost): void
    {
        $index = array_search($boost, $this->boosts, true);

        if ($index !== false) {
            array_splice($this->boosts, $index, 1);
        }

        if (count($this->boosts) === 0) {
            $this->codeShift->uninstall();
        }
    }

    /**
     * @inheritDoc
     */
    public function uninstall(): void
    {
        foreach ($this->boosts as $boost) {
            $boost->uninstall();
        }

        $this->boosts = [];
    }
}
