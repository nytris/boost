# Nytris Boost

[![Build Status](https://github.com/nytris/boost/workflows/CI/badge.svg)](https://github.com/nytris/boost/actions?query=workflow%3ACI)

Improves PHP performance, especially when `open_basedir` is in effect.

## Why?
- `open_basedir` disables the PHP realpath and stat caches, this library re-implements them in a configurable way.
- Even when `open_basedir` is disabled, the native caches are only stored per-process.
  This library allows them to be stored using a PSR-compliant cache.

Note that for the native filesystem wrapper (when this library is not in use):
- The `stat` cache only keeps a single file, the most recent stat taken.
- There is a separate similar one-stat cache for `lstat` results.

When in use, this library caches stats for all files accessed and not only the most recent one.

## Usage
Install this package with Composer:

```shell
$ composer require nytris/boost
```

### When using Nytris platform (recommended)

Configure Nytris platform:

`nytris.config.php`

```php
<?php

declare(strict_types=1);

use Nytris\Boost\BoostPackage;
use Nytris\Boot\BootConfig;
use Nytris\Boot\PlatformConfig;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$bootConfig = new BootConfig(new PlatformConfig(__DIR__ . '/var/cache/nytris/'));

$bootConfig->installPackage(new BoostPackage(
    // Allows changing to avoid collisions if required.
    realpathCacheKey: 'realpath_key',

    // Using Symfony Cache adapter as an example.
    realpathCachePoolFactory: fn (string $cachePath) => new FilesystemAdapter(
        'realpath',
        0,
        $cachePath
    ),

    // Allows changing to avoid collisions if required.
    statCacheKey: 'stat_key',

    // Using Symfony Cache adapter as an example.
    statCachePoolFactory: fn (string $cachePath) => new FilesystemAdapter(
        'stat',
        0,
        $cachePath
    ),

    // Whether to hook `clearstatcache(...)`.
    hookBuiltinFunctions: true
));

return $bootConfig;
```

### When using Boost standalone

Load Boost as early as possible in your application, for example a `/bootstrap.php`:

```php
<?php

declare(strict_types=1);

use Nytris\Boost\Boost;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

require __DIR__ . '/vendor/autoload.php';

// Install Nytris Boost as early as possible so that as many files as possible are cached.
if (getenv('ENABLE_NYTRIS_BOOST') === 'yes') {
    (new Boost(
        realpathCachePool: new FilesystemAdapter(
            'nytris.realpath',
            0,
            __DIR__ . '/var/cache/'
        ),
        statCachePool: new FilesystemAdapter(
            'nytris.stat',
            0,
            __DIR__ . '/var/cache/'
        ),
        hookBuiltinFunctions: false
    ))->install();
}

...
```

## See also
- [PHP Code Shift][1], which is used by this library.

[1]: https://github.com/asmblah/php-code-shift
