<?php
/**
 * phpunit
 */

error_reporting(E_ALL);
ini_set('display_errors', 'On');
date_default_timezone_set('Asia/Shanghai');

spl_autoload_register(static function ($class) {
    $file = null;

    if (0 === strpos($class, 'Toolkit\FsUtil\Example\\')) {
        $path = str_replace('\\', '/', substr($class, strlen('Toolkit\FsUtil\Example\\')));
        $file = dirname(__DIR__) . "/example/{$path}.php";
    } elseif (0 === strpos($class, 'Toolkit\FsUtilTest\\')) {
        $path = str_replace('\\', '/', substr($class, strlen('Toolkit\FsUtilTest\\')));
        $file = __DIR__ . "/{$path}.php";
    } elseif (0 === strpos($class, 'Toolkit\FsUtil\\')) {
        $path = str_replace('\\', '/', substr($class, strlen('Toolkit\FsUtil\\')));
        $file = dirname(__DIR__) . "/src/{$path}.php";
    }

    if ($file && is_file($file)) {
        include $file;
    }
});

if (is_file(dirname(__DIR__, 3) . '/autoload.php')) {
    require dirname(__DIR__, 3) . '/autoload.php';
} elseif (is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    require dirname(__DIR__) . '/vendor/autoload.php';
}

