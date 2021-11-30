# FileSystem Util

[![License](https://img.shields.io/packagist/l/toolkit/fsutil.svg?style=flat-square)](LICENSE)
[![Php Version](https://img.shields.io/badge/php-%3E=8.0-brightgreen.svg?maxAge=2592000)](https://packagist.org/packages/toolkit/fsutil)
[![Latest Stable Version](http://img.shields.io/packagist/v/toolkit/fsutil.svg)](https://packagist.org/packages/toolkit/fsutil)
[![Actions Status](https://github.com/php-toolkit/fsutil/workflows/Unit-tests/badge.svg)](https://github.com/php-toolkit/fsutil/actions)

Some useful file system util for php

- basic filesystem operation
- file read/write operation
- directory operation
- file modify watcher
- files finder

## Install

- Required PHP 8.0+

```bash
composer require toolkit/fsutil
```

## Usage

### File Finder

```php
use Toolkit\FsUtil\FileFinder;

$finder = FileFinder::create()
    ->files()
    ->name('*.php')
    // ->ignoreVCS(false)
    // ->ignoreDotFiles(false)
    // ->exclude('tmp')
    ->notPath('tmp')
    ->inDir(dirname(__DIR__));

foreach ($finder as $file) {
    // var_dump($file);
    echo "+ {$file->getPathname()}\n";
}
```

### Modify Watcher

```php
use Toolkit\FsUtil\ModifyWatcher;

$w  = new ModifyWatcher();
$ret = $w
    // ->setIdFile(__DIR__ . '/tmp/dir.id')
    ->watch(dirname(__DIR__))
    ->isChanged();

// d41d8cd98f00b204e9800998ecf8427e
// current file:  ae4464472e898ba0bba8dc7302b157c0
var_dump($ret, $mw->getDirMd5(), $mw->getFileCounter());
```

## License

MIT
