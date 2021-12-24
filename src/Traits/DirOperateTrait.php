<?php declare(strict_types=1);

namespace Toolkit\FsUtil\Traits;

use Closure;
use DirectoryIterator;
use LogicException;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use Toolkit\FsUtil\Exception\FileNotFoundException;
use Toolkit\FsUtil\Exception\FileSystemException;
use Toolkit\FsUtil\FileSystem;
use function closedir;
use function opendir;
use function readdir;

/**
 * Class DirOperateTrait
 *
 * @package Toolkit\FsUtil\Traits
 */
trait DirOperateTrait
{
    /**
     * Usage:
     *
     * ```php
     * $filter = Dir::getPhpFileFilter();
     *
     * // $info is instance of \SplFileInfo
     * foreach(Dir::getRecursiveIterator($srcDir, $filter) as $info) {
     *    // $info->getFilename(); ...
     * }
     * ```
     *
     * @param string   $srcDir
     * @param callable $filter
     *
     * @return RecursiveIteratorIterator
     */
    public static function getRecursiveIterator(string $srcDir, callable $filter): RecursiveIteratorIterator
    {
        return FileSystem::getIterator($srcDir, $filter);
    }

    /**
     * @return Closure
     */
    public static function getPhpFileFilter(): callable
    {
        return static function (SplFileInfo $f) {
            $name = $f->getFilename();

            // Skip hidden files and directories.
            if (str_starts_with($name, '.')) {
                return false;
            }

            // go on read sub-dir
            if ($f->isDir()) {
                return true;
            }

            // php file
            return $f->isFile() && str_ends_with($name, '.php');
        };
    }

    /**
     * 判断文件夹是否为空
     *
     * @param string $dir
     *
     * @return bool
     * @throws FileSystemException
     */
    public static function isEmpty(string $dir): bool
    {
        $handler = opendir($dir);

        if (false === $handler) {
            throw new FileSystemException("Open the dir failure! DIR: $dir");
        }

        while (($file = readdir($handler)) !== false) {
            if ($file !== '.' && $file !== '..') {
                closedir($handler);

                return false;
            }
        }

        closedir($handler);

        return true;
    }

    /**
     * 查看一个目录中的所有文件和子目录
     *
     * @param string $path
     *
     * @return array
     * @throws FileNotFoundException
     */
    public static function ls(string $path): array
    {
        $list = [];

        try {
            /*** class create new DirectoryIterator Object ***/
            foreach (new DirectoryIterator($path) as $item) {
                $list[] = $item;
            }
            /*** if an exception is thrown, catch it here ***/
        } catch (Throwable $e) {
            throw new FileNotFoundException($path . ' 没有任何内容');
        }

        return $list;
    }

}
