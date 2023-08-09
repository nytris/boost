# Nytris Boost

[![Build Status](https://github.com/nytris/boost/workflows/CI/badge.svg)](https://github.com/nytris/boost/actions?query=workflow%3ACI)

Improves PHP performance when `open_basedir` is in effect.

## Why?
- `open_basedir` disables the PHP realpath and stat caches, this library re-implements them in a configurable way.
- Even when `open_basedir` is disabled, the native caches are only stored per-process.
  This library allows them to be stored using a PSR-compliant cache.

## Usage
Install this package with Composer:

```shell
$ composer install nytris/boost
```

## See also
- [PHP Code Shift][1], which is used by this library.

[1]: https://github.com/asmblah/php-code-shift
