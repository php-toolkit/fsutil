<?php

namespace Toolkit\FsUtil\Traits;

use FilesystemIterator;
use Toolkit\FsUtil\Exception\FileSystemException;
use Toolkit\FsUtil\Exception\IOException;
use Toolkit\Stdlib\Arr;
use Traversable;
use function copy;
use function error_get_last;
use function function_exists;
use function is_dir;
use function mkdir;
use function rmdir;
use function strlen;

/**
 * Trait FileSystemFuncTrait
 *
 * @package Toolkit\FsUtil\Traits
 */
trait FileSystemFuncTrait
{
    /**
     * @param string   $source
     * @param string   $dest
     * @param resource $context
     */
    public static function copyFile(string $source, string $dest, $context = null): void
    {
        if (false === copy($source, $dest, $context)) {
            throw new FileSystemException("copy file content failure");
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
     * Change mode for an array of files or directories.
     *
     * @from Symfony-filesystem
     *
     * @param string|array|Traversable $files     A filename, an array of files, or a \Traversable instance to change mode
     * @param int                      $mode      The new mode (octal)
     * @param int                      $umask     The mode mask (octal)
     * @param bool                     $recursive Whether change the mod recursively or not
     *
     * @throws IOException When the change fail
     */
    public static function chmod($files, $mode, $umask = 0000, $recursive = false): void
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
     * @param string|array|Traversable $files     A filename, an array of files, or a \Traversable instance to change owner
     * @param string                   $user      The new owner user name
     * @param bool                     $recursive Whether change the owner recursively or not
     *
     * @throws IOException When the change fail
     */
    public static function chown($files, string $user, $recursive = false): void
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
