<?php

/*
 * Nytris Boost.
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/nytris/boost/
 *
 * Released under the MIT license.
 * https://github.com/nytris/boost/raw/main/MIT-LICENSE.txt
 */

declare(strict_types=1);

namespace Nytris\Boost\Tests\Functional;

use Mockery\MockInterface;
use Nytris\Boost\Boost;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class FilesystemWritingTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class FilesystemWritingTest extends AbstractFunctionalTestCase
{
    private ?Boost $boost;
    /**
     * @var (MockInterface&CacheItemInterface)|null
     */
    private $realpathCacheItem;
    /**
     * @var (MockInterface&CacheItemPoolInterface)|null
     */
    private $realpathCachePool;
    /**
     * @var (MockInterface&CacheItemInterface)|null
     */
    private $statCacheItem;
    /**
     * @var (MockInterface&CacheItemPoolInterface)|null
     */
    private $statCachePool;
    private ?string $varPath;

    public function setUp(): void
    {
        $this->realpathCachePool = mock(CacheItemPoolInterface::class, [
            'saveDeferred' => null,
        ]);
        $this->statCachePool = mock(CacheItemPoolInterface::class, [
            'saveDeferred' => null,
        ]);
        $this->realpathCacheItem = mock(CacheItemInterface::class, [
            'get' => [],
            'isHit' => true,
            'set' => null,
        ]);
        $this->statCacheItem = mock(CacheItemInterface::class, [
            'get' => [],
            'isHit' => true,
            'set' => null,
        ]);

        $this->varPath = realpath(__DIR__ . '/../../') . '/var';
        @mkdir($this->varPath, recursive: true);

        $this->boost = new Boost(
            realpathCachePool: $this->realpathCachePool,
            statCachePool: $this->statCachePool,
            realpathCacheKey: '__my_realpath_cache',
            statCacheKey: '__my_stat_cache'
        );

        $this->realpathCachePool->allows()
            ->getItem('__my_realpath_cache')
            ->andReturn($this->realpathCacheItem);
        $this->statCachePool->allows()
            ->getItem('__my_stat_cache')
            ->andReturn($this->statCacheItem);
    }

    public function tearDown(): void
    {
        $this->boost->uninstall();

        $this->rimrafDescendantsOf($this->varPath);
    }

    public function testWritesToUncachedFilesShouldBeHandledCorrectly(): void
    {
        $path = $this->varPath . '/my_written_script.php';
        $this->realpathCacheItem->allows()
            ->get()
            ->andReturn([]);
        $this->boost->install();

        file_put_contents($path, '<?php return "this is mystring";');
        $result = file_get_contents($path);

        static::assertSame('<?php return "this is mystring";', $result);
    }

    public function testWritesToFilesCachedAsNonExistentShouldBeHandledCorrectly(): void
    {
        $path = $this->varPath . '/my_written_script.php';
        $this->realpathCacheItem->allows()
            ->get()
            ->andReturn([
                $path => [
                    'exists' => false,
                ],
            ]);
        $this->boost->install();

        file_put_contents($path, '<?php return "this is mystring";');
        $result = file_get_contents($path);

        static::assertSame('<?php return "this is mystring";', $result);
    }

    public function testCreatingDirectoriesAtPathsCachedAsNonExistentShouldBeHandledCorrectly(): void
    {
        $path = $this->varPath . '/mydir';
        $this->realpathCacheItem->allows()
            ->get()
            ->andReturn([
                $path => [
                    'exists' => false,
                ],
            ]);
        $this->boost->install();

        mkdir($path);

        static::assertTrue(is_dir($path));
    }

    public function testTouchingFilesCachedAsNonExistentShouldBeHandledCorrectly(): void
    {
        $path = $this->varPath . '/my_written_script.php';
        $this->realpathCacheItem->allows()
            ->get()
            ->andReturn([
                $path => [
                    'exists' => false,
                ],
            ]);
        $this->boost->install();

        touch($path);

        static::assertTrue(is_file($path));
    }

    public function testRenamingFilesToTargetCachedAsNonExistentShouldBeHandledCorrectly(): void
    {
        $fromPath = $this->varPath . '/my_from_written_script.php';
        $toPath = $this->varPath . '/my_to_written_script.php';
        $this->realpathCacheItem->allows()
            ->get()
            ->andReturn([
                $fromPath => [
                    'exists' => false,
                ],
                $toPath => [
                    'exists' => false,
                ],
            ]);
        file_put_contents($fromPath, 'my contents');
        $this->boost->install();

        rename($fromPath, $toPath);

        static::assertFalse(is_file($fromPath));
        static::assertTrue(is_file($toPath));
        static::assertSame('my contents', file_get_contents($toPath));
    }

    public function testDeletingCachedFilesShouldBeHandledCorrectly(): void
    {
        $path = $this->varPath . '/my_written_script.php';
        touch($path);
        $this->boost->install();
        is_file($path); // Cause the path to be cached in the realpath & stat caches.

        unlink($path);

        static::assertFalse(is_file($path));
    }

    public function testDeletingCachedDirectoriesShouldBeHandledCorrectly(): void
    {
        $path = $this->varPath . '/mydir';
        mkdir($path);
        $this->boost->install();
        is_dir($path); // Cause the path to be cached in the realpath & stat caches.

        rmdir($path);

        static::assertFalse(is_dir($path));
    }
}
