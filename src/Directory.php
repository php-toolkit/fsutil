<?php declare(strict_types=1);
/**
 * This file is part of toolkit/fsutil.
 *
 * @author   https://github.com/inhere
 * @link     https://github.com/toolkit/fsutil
 * @license  MIT
 */

namespace Toolkit\FsUtil;

use InvalidArgumentException;
use Toolkit\FsUtil\Exception\FileNotFoundException;
use Toolkit\FsUtil\Traits\DirOperateTrait;
use function array_merge;
use function basename;
use function glob;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function preg_match;
use function strlen;
use function trim;
use const GLOB_BRACE;

/**
 * Class Directory
 *
 * @package Toolkit\FsUtil
 */
class Directory extends FileSystem
{
    use DirOperateTrait;

    /**
     * 只获得目录结构
     *
     * @param string $path
     * @param int $pid
     * @param bool $son
     * @param array $list
     *
     * @return array
     * @throws FileNotFoundException
     */
    public static function getList(string $path, int $pid = 0, bool $son = false, array $list = []): array
    {
        $path = self::pathFormat($path);
        if (!is_dir($path)) {
            throw new FileNotFoundException("directory not exists! DIR: $path");
        }

        static $id = 0;

        foreach (glob($path . '*') as $v) {
            if (is_dir($v)) {
                $id++;

                $list[$id]['id']   = $id;
                $list[$id]['pid']  = $pid;
                $list[$id]['name'] = basename($v);
                $list[$id]['path'] = realpath($v);

                //是否遍历子目录
                if ($son) {
                    $list = self::getList($v, $id, $son, $list);
                }
            }
        }

        return $list;
    }

    /**
     * @param string $path
     * @param bool $loop
     * @param null $parent
     * @param array $list
     *
     * @return array
     */
    public static function getDirs(string $path, bool $loop = false, $parent = null, array $list = []): array
    {
        $path = self::pathFormat($path);

        if (!is_dir($path)) {
            throw new FileNotFoundException("directory not exists! DIR: $path");
        }

        $len = strlen($path);
        foreach (glob($path . '*') as $v) {
            if (is_dir($v)) {
                $relatePath = substr($v, $len);
                $list[]     = $parent . $relatePath;

                //是否遍历子目录
                if ($loop) {
                    $list = self::getDirs($v, $loop, $relatePath . '/', $list);
                }
            }
        }

        return $list;
    }

    /**
     * 获得目录下的文件，可选择类型、是否遍历子文件夹
     *
     * @param string $dir string 目标目录
     * @param array|string $ext array('css','html','php') css|html|php
     * @param bool $recursive int|bool 是否包含子目录
     *
     * @return array
     * @throws FileNotFoundException
     */
    public static function simpleInfo(string $dir, array|string $ext = '', bool $recursive = false): array
    {
        $list = [];
        $dir  = self::pathFormat($dir);
        $ext  = is_array($ext) ? implode('|', $ext) : trim($ext);

        if (!is_dir($dir)) {
            throw new FileNotFoundException("directory not exists! DIR: $dir");
        }

        // glob()寻找与模式匹配的文件路径 $file is pull path
        foreach (glob($dir . '*') as $file) {
            // 匹配文件 如果没有传入$ext 则全部遍历，传入了则按传入的类型来查找
            if (is_file($file) && (!$ext || preg_match("/\.($ext)$/i", $file))) {
                //basename — 返回路径中的 文件名部分
                $list[] = basename($file);

                // is directory
            } else {
                $list[] = '/' . basename($file);

                if ($recursive && is_dir($file)) {
                    /** @noinspection SlowArrayOperationsInLoopInspection */
                    $list = array_merge($list, self::simpleInfo($file, $ext, $recursive));
                }
            }
        }

        return $list;
    }

    /**
     * 获得目录下的文件，可选择类型、是否遍历子文件夹
     *
     * @param string $path string 目标目录
     * @param array|string $ext array('css','html','php') css|html|php
     * @param bool $recursive 是否包含子目录
     * @param string $parent
     * @param array $list
     *
     * @return array
     * @throws FileNotFoundException
     */
    public static function getFiles(
        string $path,
        array|string $ext = '',
        bool $recursive = false,
        string $parent = '',
        array $list = []
    ): array {
        $path = self::pathFormat($path);

        if (!is_dir($path)) {
            throw new FileNotFoundException("directory not exists! DIR: $path");
        }

        $len = strlen($path);
        $ext = is_array($ext) ? implode('|', $ext) : trim($ext);

        foreach (glob($path . '*') as $v) {
            $relatePath = substr($v, $len);

            // 匹配文件 如果没有传入$ext 则全部遍历，传入了则按传入的类型来查找
            if (is_file($v) && (!$ext || preg_match("/\.($ext)$/i", $v))) {
                $list[] = $parent . $relatePath;
            } elseif ($recursive && is_dir($v)) {
                $list = self::getFiles($v, $ext, $recursive, $relatePath . '/', $list);
            }
        }

        return $list;
    }

    /**
     * 获得目录下的文件以及详细信息，可选择类型、是否遍历子文件夹
     *
     * @param string $path string 目标目录
     * @param array|string $ext array('css','html','php') css|html|php
     * @param bool $recursive 是否包含子目录
     * @param array $list
     *
     * @return array
     * @throws InvalidArgumentException
     * @throws FileNotFoundException
     */
    public static function getFilesInfo(string $path, array|string $ext = '', bool $recursive = false, array &$list = []): array
    {
        $path = self::pathFormat($path);
        if (!is_dir($path)) {
            throw new FileNotFoundException("directory not exists! DIR: $path");
        }

        static $id = 0;
        $ext = is_array($ext) ? implode('|', $ext) : trim($ext);

        // glob()寻找与模式匹配的文件路径
        foreach (glob($path . '*') as $file) {
            $id++;

            // 匹配文件 如果没有传入$ext 则全部遍历，传入了则按传入的类型来查找
            if (is_file($file) && (!$ext || preg_match("/\.($ext)$/i", $file))) {
                $list[$id] = File::info($file);

                // 是否遍历子目录
            } elseif ($recursive && is_dir($file)) {
                $list = self::getFilesInfo($file, $ext, $recursive, $list);
            }
        }

        return $list;
    }

    /**
     * 支持层级目录的创建
     *
     * @param string $path
     * @param int $mode
     * @param bool $recursive
     *
     * @return bool
     */
    public static function create(string $path, int $mode = 0765, bool $recursive = true): bool
    {
        return (is_dir($path) || !(!@mkdir($path, $mode, $recursive) && !is_dir($path))) && is_writable($path);
    }

    /**
     * Quick make sub-dirs in the given parent dir.
     *
     * @param string $parentDir
     * @param array $subDirs
     * @param int $mode
     *
     * @return bool
     */
    public static function mkSubDirs(string $parentDir, array $subDirs, int $mode = 0666): bool
    {
        if (!self::create($parentDir)) {
            return false;
        }

        foreach ($subDirs as $subPath) {
            self::create($parentDir . '/' . $subPath, $mode);
        }

        return true;
    }

    /**
     * Copy dir files, contains sub-dir.
     *
     * ### $options
     *
     * - skipExist: bool, whether skip exist file.
     * - filterFn: callback func on handle each file.
     * - beforeFn: callback func on before copy file.
     * - afterFn: callback func on after copy file.
     *
     * @param string $oldDir source directory path.
     * @param string $newDir target directory path.
     * @param array $options = [
     *     'skipExist' => true,
     *     'filterFn' => function (string $old): bool { },
     *     'beforeFn' => function (string $old, string $new): bool { },
     *     'afterFn' => function (string $new): void { },
     * ]
     *
     * @return bool
     */
    public static function copy(string $oldDir, string $newDir, array $options = []): bool
    {
        if (!is_dir($oldDir)) {
            throw new FileNotFoundException("copy error：source dir does not exist！path: $oldDir");
        }

        self::doCopy($oldDir, $newDir, array_merge([
            'skipExist' => true,
            'filterFn'  => null,
            'beforeFn'  => null,
            'afterFn'   => null,
        ], $options));

        return true;
    }

    /**
     * @param string $oldDir
     * @param string $newDir
     * @param array $options
     *
     * @return void
     */
    private static function doCopy(string $oldDir, string $newDir, array $options): void
    {
        self::create($newDir);
        $beforeFn = $options['beforeFn'];
        $filterFn = $options['filterFn'];

        // use '{,.}*' match hidden files
        foreach (glob($oldDir . '/{,.}*', GLOB_BRACE) as $old) {
            $name = basename($old);
            if ($name === '.' || $name === '..') {
                continue;
            }

            $new = self::joinPath($newDir, $name);

            if (is_dir($old)) {
                self::doCopy($old, $new, $options);
                continue;
            }

            // return false to skip copy
            if ($filterFn && !$filterFn($old)) {
                continue;
            }

            // return false to skip copy
            if ($beforeFn && !$beforeFn($old, $new)) {
                continue;
            }

            if ($options['skipExist'] && file_exists($new)) {
                continue;
            }

            // do copy
            copy($old, $new);
            @chmod($new, 0664); // 权限 0777

            if ($afterFn = $options['afterFn']) {
                $afterFn($new);
            }
        }
    }

    /**
     * 删除目录及里面的文件
     *
     * @param string $path
     * @param boolean $delSelf 默认最后删掉自己
     *
     * @return bool
     */
    public static function delete(string $path, bool $delSelf = true): bool
    {
        $dirPath = self::pathFormat($path);

        if (is_file($dirPath)) {
            return unlink($dirPath);
        }

        foreach (glob($dirPath . '*') as $v) {
            is_dir($v) ? self::delete($v) : unlink($v);
        }

        $delSelf && rmdir($dirPath);//默认最后删掉自己
        return true;
    }
}
