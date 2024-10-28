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
use Symfony\Component\Cache\Adapter\ApcuAdapter;

$bootConfig = new BootConfig(new PlatformConfig(__DIR__ . '/var/cache/nytris/'));

$bootConfig->installPackage(new BoostPackage(
    // Allows changing to avoid collisions if required.
    realpathCacheKey: 'realpath_key',

    // Using Symfony Cache adapter as an example.
    realpathCachePoolFactory: fn (string $cachePath) => new ApcuAdapter(
        namespace: 'realpath',
        defaultLifetime: 0
    ),

    // Allows changing to avoid collisions if required.
    statCacheKey: 'stat_key',

    // Using Symfony Cache adapter as an example.
    statCachePoolFactory: fn (string $cachePath) => new ApcuAdapter(
        namespace: 'stat',
        defaultLifetime: 0
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
use Symfony\Component\Cache\Adapter\ApcuAdapter;

require __DIR__ . '/vendor/autoload.php';

// Install Nytris Boost as early as possible so that as many files as possible are cached.
if (getenv('ENABLE_NYTRIS_BOOST') === 'yes') {
    (new Boost(
        realpathCachePool: new ApcuAdapter(
            namespace: 'nytris.realpath',
            defaultLifetime: 0
        ),
        statCachePool: new ApcuAdapter(
            namespace: 'nytris.stat',
            defaultLifetime: 0
        ),
        hookBuiltinFunctions: false
    ))->install();
}

...
```

## Known issues / limitations
Using a filesystem-based cache such as [Symfony Cache's `FilesystemAdapter`][2]
for the realpath or stat caches, for example, may cause infinite recursion,
which can result in a segfault even with [Xdebug][3] enabled.

The solution is to avoid using filesystem-based caches for the filesystem data caches,
which makes little sense in any case when the purpose of Boost is to reduce or avoid filesystem I/O.

## See also
- [PHP Code Shift][1], which is used by this library.

[1]: https://github.com/asmblah/php-code-shift
[2]: https://symfony.com/doc/current/components/cache/adapters/filesystem_adapter.html
[3]: https://xdebug.org/
