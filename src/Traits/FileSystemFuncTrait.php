<?php declare(strict_types=1);
/**
 * This file is part of toolkit/fsutil.
 *
 * @author   https://github.com/inhere
 * @link     https://github.com/toolkit/fsutil
 * @license  MIT
 */

namespace Toolkit\FsUtil\Traits;

use FilesystemIterator;
use InvalidArgumentException;
use Toolkit\FsUtil\Exception\FileSystemException;
use Toolkit\FsUtil\Exception\IOException;
use Toolkit\Stdlib\Arr;
use Toolkit\Stdlib\OS;
use Traversable;
use function array_filter;
use function array_pop;
use function array_values;
use function copy;
use function error_get_last;
use function explode;
use function function_exists;
use function get_resource_type;
use function getcwd;
use function implode;
use function is_dir;
use function is_resource;
use function is_writable;
use function mkdir;
use function realpath;
use function rmdir;
use function str_replace;
use function stream_get_meta_data;
use function strlen;
use const DIRECTORY_SEPARATOR;

/**
 * Trait FileSystemFuncTrait
 *
 * @package Toolkit\FsUtil\Traits
 */
trait FileSystemFuncTrait
{

    /**
     * @param resource $stream
     *
     * @return bool
     */
    public static function isStream($stream): bool
    {
        return is_resource($stream) && get_resource_type($stream) === 'stream';
    }

    /**
     * @param resource $stream
     */
    public static function assertStream($stream): void
    {
        if (!self::isStream($stream)) {
            throw new InvalidArgumentException('Expected a valid stream');
        }
    }

    /**
     * @param resource $stream
     */
    public static function assertReadableStream($stream): void
    {
        if (!self::isStream($stream)) {
            throw new InvalidArgumentException('Expected a valid stream');
        }

        $meta = stream_get_meta_data($stream);
        if (!str_contains($meta['mode'], 'r') && !str_contains($meta['mode'], '+')) {
            throw new InvalidArgumentException('Expected a readable stream');
        }
    }

    /**
     * @param resource $stream
     */
    public static function assertWritableStream($stream): void
    {
        if (!self::isStream($stream)) {
            throw new InvalidArgumentException('Expected a valid stream');
        }

        $meta = stream_get_meta_data($stream);
        if (!str_contains($meta['mode'], 'w') && !str_contains($meta['mode'], '+')) {
            throw new InvalidArgumentException('Expected a writable stream');
        }
    }

    /**
     * @param string   $source
     * @param string   $dest
     * @param resource $context
     */
    public static function copyFile(string $source, string $dest, $context = null): void
    {
        if (false === copy($source, $dest, $context)) {
            throw new FileSystemException('copy file content failure');
        }
    }

    /**
     * @param string   $filepath
     * @param resource $context
     */
    public static function unlink(string $filepath, $context = null): void
    {
        if (false === unlink($filepath, $context)) {
            throw new FileSystemException("delete file failure, path: $filepath");
        }
    }

    /**
     * Renames a file or a directory.
     *
     * @from Symfony-filesystem
     *
     * @param string $origin    The origin filename or directory
     * @param string $target    The new filename or directory
     * @param bool   $overwrite Whether to overwrite the target if it already exists
     *
     * @throws IOException When target file or directory already exists
     * @throws IOException When origin cannot be renamed
     */
    public static function rename(string $origin, string $target, bool $overwrite = false): void
    {
        // we check that target does not exist
        if (!$overwrite && static::isReadable($target)) {
            throw new IOException(sprintf('Cannot rename because the target "%s" already exists.', $target));
        }

        if (true !== rename($origin, $target)) {
            throw new IOException(sprintf('Cannot rename "%s" to "%s".', $origin, $target));
        }
    }

    /**
     * Tells whether a file exists and is readable.
     *
     * @from Symfony-filesystem
     *
     * @param string $filename Path to the file
     *
     * @return bool
     * @throws IOException When windows path is longer than 258 characters
     */
    public static function isReadable(string $filename): bool
    {
        if ('\\' === DIRECTORY_SEPARATOR && strlen($filename) > 258) {
            throw new IOException('Could not check if file is readable because path length exceeds 258 characters.');
        }

        return is_readable($filename);
    }

    /**
     * @param string $filename
     *
     * @return bool
     */
    public static function isWriteable(string $filename): bool
    {
        if ('\\' === DIRECTORY_SEPARATOR && strlen($filename) > 258) {
            throw new IOException('Could not check if file is readable because path length exceeds 258 characters.');
        }

        return is_writable($filename);
    }

    /**
     * Change mode for an array of files or directories.
     *
     * @from Symfony-filesystem
     *
     * @param Traversable|array|string $files     A filename, an array of files, or a \Traversable instance to change mode
     * @param int                      $mode      The new mode (octal)
     * @param int                      $umask     The mode mask (octal)
     * @param bool                     $recursive Whether change the mod recursively or not
     *
     * @throws IOException When the change fail
     */
    public static function chmod(Traversable|array|string $files, int $mode, int $umask = 0000, bool $recursive = false): void
    {
        foreach (Arr::toIterator($files) as $file) {
            if (true !== @chmod($file, $mode & ~$umask)) {
                throw new IOException(sprintf('Failed to chmod file "%s".', $file));
            }

            if ($recursive && is_dir($file) && !is_link($file)) {
                self::chmod(new FilesystemIterator($file), $mode, $umask, true);
            }
        }
    }

    /**
     * Change the owner of an array of files or directories.
     *
     * @from Symfony-filesystem
     *
     * @param Traversable|array|string $files     A filename, an array of files, or a \Traversable instance to change owner
     * @param string                   $user      The new owner user name
     * @param bool                     $recursive Whether change the owner recursively or not
     *
     * @throws IOException When the change fail
     */
    public static function chown(Traversable|array|string $files, string $user, bool $recursive = false): void
    {
        foreach (Arr::toIterator($files) as $file) {
            if ($recursive && is_dir($file) && !is_link($file)) {
                self::chown(new FilesystemIterator($file), $user, true);
            }

            if (function_exists('lchown') && is_link($file)) {
                if (true !== lchown($file, $user)) {
                    throw new IOException(sprintf('Failed to chown file "%s".', $file));
                }
            } elseif (true !== chown($file, $user)) {
                throw new IOException(sprintf('Failed to chown file "%s".', $file));
            }
        }
    }

    /**
     * clear invalid sep and will parse ~ as user home dir.
     *
     * @param string $path
     *
     * @return string
     */
    public static function expandPath(string $path): string
    {
        return realpath($path);
    }

    /**
     * clear invalid sep and will parse ~ as user home dir.
     *
     * @param string $path
     *
     * @return string
     * @see realpath()
     * @link https://www.php.net/manual/zh/function.realpath.php#84012
     */
    public static function realpath(string $path): string
    {
        $path  = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        if (!$parts = array_values($parts)) {
            return '';
        }

        // ~: is user home dir in OS
        if ($parts[0] === '~') {
            $parts[0] = OS::getUserHomeDir();
        }

        // `.` is relative path
        if ($parts[0] === '.') {
            $parts[0] = (string)getcwd();
        }

        $absolutes = [];
        foreach ($parts as $part) {
            if ('.' === $part) {
                continue;
            }

            if ('..' === $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }

        $fullPath = implode(DIRECTORY_SEPARATOR, $absolutes);

        // is unix like OS
        if (DIRECTORY_SEPARATOR === '/' && $fullPath[0] !== '/') {
            return '/' . $fullPath;
        }

        return $fullPath;
    }

    /**********************************************************************************
     * dir functions
     *********************************************************************************/

    /**
     * Creates a directory recursively.
     *
     * @param string   $dirPath
     * @param int      $mode
     * @param bool     $recursive
     * @param resource $context
     */
    public static function mkdir(string $dirPath, int $mode = 0775, bool $recursive = true, $context = null): void
    {
        if (is_dir($dirPath)) {
            return;
        }

        if (!@mkdir($dirPath, $mode, $recursive, $context) && !is_dir($dirPath)) {
            $error = error_get_last();

            if (!is_dir($dirPath)) {
                // The directory was not created by a concurrent process. Let's throw an exception with a developer friendly error message if we have one
                if ($error) {
                    throw new IOException(sprintf('Failed to create "%s": %s.', $dirPath, $error['message']));
                }

                throw new IOException(sprintf('Failed to create "%s"', $dirPath));
            }
        }
    }

    /**
     * @param string   $dirPath
     * @param resource $context
     */
    public static function rmdir(string $dirPath, $context = null): void
    {
        if (false === rmdir($dirPath, $context)) {
            throw new FileSystemException("remove directory failure, path: $dirPath");
        }
    }
}
