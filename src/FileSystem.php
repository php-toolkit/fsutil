<?php
/**
 * Created by sublime 3.
 * Auth: Inhere
 * Date: 15-1-14
 * Time: 10:35
 * AbstractFileSystem.php.php
 * Uesd: 主要功能是 hi
 */

namespace Toolkit\FsUtil;

use InvalidArgumentException;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Toolkit\FsUtil\Exception\FileNotFoundException;
use Toolkit\FsUtil\Traits\FileSystemFuncTrait;
use Toolkit\Stdlib\OS;
use function count;
use function file_exists;
use function is_array;
use function is_string;
use function preg_match;
use function str_ireplace;
use function strlen;
use function strpos;
use function substr;

/**
 * Class FileSystem
 *
 * @package Toolkit\FsUtil
 */
abstract class FileSystem
{
    use FileSystemFuncTrait;

    /**
     * @param $path
     *
     * @return bool
     */
    public static function isAbsPath(string $path): bool
    {
        if (!$path || !is_string($path)) {
            return false;
        }

        if (strpos($path, '/') === 0 ||  // linux/mac
            1 === preg_match('#^[a-z]:[\/|\\\]{1}.+#i', $path) // windows
        ) {
            return true;
        }

        return false;
    }

    /**
     * Returns whether the file path is an absolute path.
     *
     * @from Symfony-filesystem
     *
     * @param string $file A file path
     *
     * @return bool
     */
    public static function isAbsolutePath(string $file): bool
    {
        return strspn($file, '/\\', 0, 1) ||
            (strlen($file) > 3 && ctype_alpha($file[0]) && $file[1] === ':' && strspn($file, '/\\', 2, 1)) ||
            null !== parse_url($file, PHP_URL_SCHEME);
    }

    /**
     * 转换为标准的路径结构
     *
     * @param string $dirName
     *
     * @return string
     */
    public static function pathFormat(string $dirName): string
    {
        $dirName = (string)str_ireplace('\\', '/', trim($dirName));

        return substr($dirName, -1) === '/' ? $dirName : $dirName . '/';
    }

    /**
     * @param string $path e.g phar://E:/workenv/xxx/yyy/app.phar/web
     *
     * @return string
     */
    public function clearPharPath(string $path): string
    {
        if (strpos($path, 'phar://') === 0) {
            $path = (string)substr($path, 7);

            if (strpos($path, '.phar')) {
                return preg_replace('//[\w-]+\.phar/', '', $path);
            }
        }

        return $path;
    }

    /**
     * 检查文件/夹/链接是否存在
     *
     * @param string      $file 要检查的目标
     * @param null|string $type
     *
     * @return array|string
     */
    public static function exists(string $file, string $type = null)
    {
        if (!$type) {
            return file_exists($file);
        }

        $ret = false;

        if ($type === 'file') {
            $ret = is_file($file);
        } elseif ($type === 'dir') {
            $ret = is_dir($file);
        } elseif ($type === 'link') {
            $ret = is_link($file);
        }

        return $ret;
    }

    /**
     * @param string            $file
     * @param null|string|array $ext eg: 'jpg|gif'
     *
     * @throws FileNotFoundException
     * @throws InvalidArgumentException
     */
    public static function check(string $file, $ext = null): void
    {
        if (!$file || !file_exists($file)) {
            throw new FileNotFoundException("File {$file} not exists！");
        }

        if ($ext) {
            if (is_array($ext)) {
                $ext = implode('|', $ext);
            }

            if (preg_match("/\.($ext)$/i", $file)) {
                throw new InvalidArgumentException("{$file} extension is not match: {$ext}");
            }
        }
    }

    /**
     * @param string   $srcDir
     * @param callable $filter
     *
     * @return RecursiveIteratorIterator
     * @throws InvalidArgumentException
     */
    public static function getIterator(string $srcDir, callable $filter): RecursiveIteratorIterator
    {
        if (!$srcDir || !file_exists($srcDir)) {
            throw new InvalidArgumentException('Please provide a exists source directory.');
        }

        $directory      = new RecursiveDirectoryIterator($srcDir);
        $filterIterator = new RecursiveCallbackFilterIterator($directory, $filter);

        return new RecursiveIteratorIterator($filterIterator);
    }

    /**
     * @param     $path
     * @param int $mode
     *
     * @return bool
     */
    public static function chmodDir(string $path, $mode = 0664): bool
    {
        if (!is_dir($path)) {
            return @chmod($path, $mode);
        }

        $dh = opendir($path);
        while (($file = readdir($dh)) !== false) {
            if ($file !== '.' && $file !== '..') {
                $fullPath = $path . '/' . $file;
                if (is_link($fullPath)) {
                    return false;
                }

                if (!is_dir($fullPath) && !@chmod($fullPath, $mode)) {
                    return false;
                }

                if (!self::chmodDir($fullPath, $mode)) {
                    return false;
                }
            }
        }

        closedir($dh);

        return @chmod($path, $mode) ? true : false;
    }

    /**
     * @param string $dir
     *
     * @return string
     */
    public static function availableSpace(string $dir = '.'): string
    {
        $base   = 1024;
        $bytes  = disk_free_space($dir);
        $suffix = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $class  = min((int)log($bytes, $base), count($suffix) - 1);

        //echo $bytes . '<br />';

        // pow($base, $class)
        return sprintf('%1.2f', $bytes / ($base ** $class)) . ' ' . $suffix[$class];
    }

    /**
     * @param string $dir
     *
     * @return string
     */
    public static function countSpace(string $dir = '.'): string
    {
        $base   = 1024;
        $bytes  = disk_total_space($dir);
        $suffix = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $class  = min((int)log($bytes, $base), count($suffix) - 1);

        // pow($base, $class)
        return sprintf('%1.2f', $bytes / ($base ** $class)) . ' ' . $suffix[$class];
    }

    /**
     * 文件或目录权限检查函数
     *
     * @from   web
     * @access public
     *
     * @param string $filepath 文件路径
     *
     * @return int  返回值的取值范围为{0 <= x <= 15}，每个值表示的含义可由四位二进制数组合推出。
     *                  返回值在二进制计数法中，四位由高到低分别代表
     *                  可执行rename()函数权限 |可对文件追加内容权限 |可写入文件权限|可读取文件权限。
     */
    public static function pathModeInfo(string $filepath): int
    {
        /* 如果不存在，则不可读、不可写、不可改 */
        if (!file_exists($filepath)) {
            return false;
        }

        $mark = 0;

        if (OS::isWindows()) {
            /* 测试文件 */
            $test_file = $filepath . '/cf_test.txt';

            /* 如果是目录 */
            if (is_dir($filepath)) {
                /* 检查目录是否可读 */
                $dir = @opendir($filepath);

                //如果目录打开失败，直接返回目录不可修改、不可写、不可读
                if ($dir === false) {
                    return $mark;
                }

                //目录可读 001，目录不可读 000
                if (@readdir($dir) !== false) {
                    $mark ^= 1;
                }

                @closedir($dir);

                /* 检查目录是否可写 */
                $fp = @fopen($test_file, 'wb');

                //如果目录中的文件创建失败，返回不可写。
                if ($fp === false) {
                    return $mark;
                }

                //目录可写可读 011，目录可写不可读 010
                if (@fwrite($fp, 'directory access testing.') !== false) {
                    $mark ^= 2;
                }

                @fclose($fp);
                @unlink($test_file);

                /* 检查目录是否可修改 */
                $fp = @fopen($test_file, 'ab+');
                if ($fp === false) {
                    return $mark;
                }

                if (@fwrite($fp, "modify test.\r\n") !== false) {
                    $mark ^= 4;
                }

                @fclose($fp);

                /* 检查目录下是否有执行rename()函数的权限 */
                if (@rename($test_file, $test_file) !== false) {
                    $mark ^= 8;
                }

                @unlink($test_file);

                /* 如果是文件 */
            } elseif (is_file($filepath)) {
                /* 以读方式打开 */
                $fp = @fopen($filepath, 'rb');
                if ($fp) {
                    $mark ^= 1; //可读 001
                }

                @fclose($fp);

                /* 试着修改文件 */
                $fp = @fopen($filepath, 'ab+');
                if ($fp && @fwrite($fp, '') !== false) {
                    $mark ^= 6; //可修改可写可读 111，不可修改可写可读011...
                }

                @fclose($fp);

                /* 检查目录下是否有执行rename()函数的权限 */
                if (@rename($test_file, $test_file) !== false) {
                    $mark ^= 8;
                }
            }
        } else {
            if (@is_readable($filepath)) {
                $mark ^= 1;
            }

            if (@is_writable($filepath)) {
                $mark ^= 14;
            }
        }

        return $mark;
    }
}
