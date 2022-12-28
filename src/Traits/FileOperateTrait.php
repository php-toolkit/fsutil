<?php declare(strict_types=1);

namespace Toolkit\FsUtil\Traits;

use InvalidArgumentException;
use Toolkit\FsUtil\Directory;
use Toolkit\FsUtil\Exception\FileNotFoundException;
use Toolkit\FsUtil\Exception\FileSystemException;
use Toolkit\FsUtil\Exception\IOException;
use function basename;
use function copy;
use function dirname;
use function fclose;
use function file_put_contents;
use function fileatime;
use function filectime;
use function filesize;
use function filetype;
use function finfo_file;
use function finfo_open;
use function fopen;
use function is_file;
use function is_readable;
use function is_writable;
use function pathinfo;
use function preg_match;
use function stat;
use function str_contains;
use function str_ends_with;
use function stream_get_contents;
use function stream_set_blocking;
use function strrchr;
use function strstr;
use function trim;
use function unlink;
use const FILEINFO_MIME_TYPE;
use const PATHINFO_EXTENSION;

/**
 * Trait FileOperateTrait
 *
 * @package Toolkit\FsUtil\Traits
 */
trait FileOperateTrait
{

    /**
     * 获得文件名称
     *
     * @param string $file
     * @param bool   $clearExt 是否去掉文件名中的后缀，仅保留名字
     *
     * @return string
     */
    public static function getName(string $file, bool $clearExt = false): string
    {
        if (!str_contains($file, '.')) {
            return basename($file);
        }

        $filename = basename($file);

        return $clearExt ? strstr($filename, '.', true) : $filename;
    }

    /**
     * 获得文件扩展名、后缀名
     *
     * @param string $filename
     * @param bool   $clearPoint 是否带点
     *
     * @return string
     */
    public static function getSuffix(string $filename, bool $clearPoint = false): string
    {
        $suffix = strrchr($filename, '.');

        return $clearPoint ? trim($suffix, '.') : $suffix;
    }

    /**
     * @param string $filePath file path
     * @param string $suffix suffix name, with '.'. eg: .json
     *
     * @return string
     */
    public static function appendSuffix(string $filePath, string $suffix): string
    {
        if (!str_ends_with($filePath, $suffix)) {
            $filePath .= $suffix;
        }
        return $filePath;
    }

    /**
     * 获得文件扩展名、后缀名
     *
     * @param string $path
     * @param bool   $clearPoint 是否带点
     *
     * @return string
     */
    public static function getExtension(string $path, bool $clearPoint = false): string
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        return $clearPoint ? $ext : '.' . $ext;
    }

    /**
     * @param string $file
     *
     * @return string eg: image/gif
     */
    public static function mimeType(string $file): string
    {
        return finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file);
    }

    /**
     * @param string $filename
     * @param bool   $check
     *
     * @return array
     * @throws FileNotFoundException
     * @throws InvalidArgumentException
     */
    public static function info(string $filename, bool $check = true): array
    {
        $check && self::check($filename);

        return [
            'name'            => basename($filename), //文件名
            'type'            => filetype($filename), //类型
            'size'            => (filesize($filename) / 1000) . ' Kb', //大小
            'is_write'        => is_writable($filename) ? 'true' : 'false', //可写
            'is_read'         => is_readable($filename) ? 'true' : 'false',//可读
            'update_time'     => filectime($filename), //修改时间
            'last_visit_time' => fileatime($filename), //文件的上次访问时间
        ];
    }

    /**
     * @param string $filename
     *
     * @return array
     */
    public static function getStat(string $filename): array
    {
        return stat($filename);
    }

    /**
     * @return string
     */
    public static function readStdinBody(): string
    {
        $text  = '';
        $stdin = fopen('php://stdin', 'rb');

        if (stream_set_blocking($stdin, false)) {
            $text = stream_get_contents($stdin);
        }

        fclose($stdin);
        return $text;
    }

    /**
     * @param $filename
     *
     * @return bool
     * @throws InvalidArgumentException
     * @throws FileNotFoundException
     */
    public static function delete($filename): bool
    {
        return self::check($filename) && unlink($filename);
    }

    /**
     * @param string $file
     * @param string $target
     *
     * @throws FileNotFoundException
     * @throws FileSystemException
     * @throws IOException
     */
    public static function move(string $file, string $target): void
    {
        Directory::mkdir(dirname($target));

        if (self::copy($file, $target)) {
            unlink($file);
        }
    }

    /**
     * @param string $source
     * @param string $destination
     * @param resource|null $streamContext
     *
     * @return bool
     */
    public static function copy(string $source, string $destination, $streamContext = null): bool
    {
        if (null === $streamContext && !preg_match('/^https?:\/\//', $source)) {
            if (!is_file($source)) {
                throw new FileSystemException("Source file don't exists. File: $source");
            }

            return copy($source, $destination);
        }

        return (bool)file_put_contents($destination, self::getContentsV2($source, false, $streamContext));
    }

}
