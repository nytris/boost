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

namespace Nytris\Boost\Tests\Functional\VirtualFilesystemMode;

use Asmblah\PhpCodeShift\CodeShift;
use Asmblah\PhpCodeShift\CodeShiftInterface;
use Asmblah\PhpCodeShift\Shifter\Filter\FileFilter;
use Asmblah\PhpCodeShift\Shifter\Shift\Shift\String\StringLiteralShiftSpec;
use Nytris\Boost\Boost;
use Nytris\Boost\FsCache\Contents\ContentsCacheInterface;
use Nytris\Boost\FsCache\Contents\SinglePoolContentsCache;
use Nytris\Boost\Tests\Functional\AbstractFunctionalTestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Class IncludeTest.
 *
 * Tests the behaviour of PHP module inclusion with Nytris Boost in virtual filesystem mode.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class IncludeTest extends AbstractFunctionalTestCase
{
    private Boost $boost;
    private CodeShiftInterface $codeShift;
    private ContentsCacheInterface $contentsCache;
    private CacheItemPoolInterface $realpathCachePool;
    private CacheItemPoolInterface $statCachePool;

    public function setUp(): void
    {
        $this->codeShift = new CodeShift();
        $this->contentsCache = new SinglePoolContentsCache(new ArrayAdapter());
        $this->realpathCachePool = new ArrayAdapter();
        $this->statCachePool = new ArrayAdapter();

        $this->boost = new Boost(
            realpathCachePool: $this->realpathCachePool,
            statCachePool: $this->statCachePool,
            realpathCacheKey: '__my_realpath_cache',
            statCacheKey: '__my_stat_cache',
            contentsCache: $this->contentsCache,
            // Avoid affecting test harness filesystem access, e.g. when autoloading Mockery classes.
            pathFilter: new FileFilter('/my/**'),
            asVirtualFilesystem: true
        );
    }

    public function tearDown(): void
    {
        $this->boost->uninstall();
        $this->codeShift->uninstall();
    }

    public function testShiftsMayBeAppliedToModulesInVirtualFilesystem(): void
    {
        $modulePath = '/my/path/to/my_module.php';
        $this->codeShift->shift(
            new StringLiteralShiftSpec('hello there', 'well good day to you'),
            new FileFilter('/my/**')
        );
        $this->boost->install();
        mkdir(dirname($modulePath), recursive: true);
        file_put_contents($modulePath, <<<PHP
<?php

return 'I said hello there';
PHP);

        static::assertSame(
            'I said well good day to you',
            include $modulePath
        );
    }
}
