<?php declare(strict_types=1);

namespace Toolkit\FsUtil\Traits;

use DirectoryIterator;
use LogicException;
use RecursiveIteratorIterator;
use Throwable;
use Toolkit\FsUtil\Exception\FileNotFoundException;
use Toolkit\FsUtil\Exception\FileSystemException;
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
     * ```php
     * $filter = function ($current, $key, $iterator) {
     *  // \SplFileInfo $current
     *  // Skip hidden files and directories.
     *  if ($current->getFilename()[0] === '.') {
     *      return false;
     *  }
     *  if ($current->isDir()) {
     *      // Only recurse into intended subdirectories.
     *      return $current->getFilename() !== '.git';
     *  }
     *      // Only consume files of interest.
     *      return strpos($current->getFilename(), '.php') !== false;
     * };
     *
     * // $info is instance of \SplFileInfo
     * foreach(Directory::getRecursiveIterator($srcDir, $filter) as $info) {
     *    // $info->getFilename(); ...
     * }
     * ```
     *
     * @param string   $srcDir
     * @param callable $filter
     *
     * @return RecursiveIteratorIterator
     * @throws LogicException
     */
    public static function getRecursiveIterator(string $srcDir, callable $filter): RecursiveIteratorIterator
    {
        return self::getIterator($srcDir, $filter);
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
