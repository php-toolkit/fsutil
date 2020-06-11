<?php

namespace Toolkit\FsUtil\Traits;

use Toolkit\FsUtil\Exception\FileSystemException;
use Toolkit\FsUtil\Exception\IOException;
use Toolkit\Stdlib\Arr;
use function copy;
use function error_get_last;
use function is_dir;
use function mkdir;
use function rmdir;

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
    public static function copy(string $source, string $dest, $context = null): void
    {
        if (false === copy($source, $dest, $context)) {
            throw new FileSystemException("copy file content failure");
        }
    }

    /**
     * @param string $filepath
     * @param  resource $context
     */
    public static function unlink(string $filepath, $context = null): void
    {
        if (false === unlink($filepath, $context)) {
            throw new FileSystemException("delete file failure, path: $filepath");
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
     * @param string $dirPath
     * @param resource $context
     */
    public static function rmdir(string $dirPath, $context = null): void
    {
        if (false === rmdir($dirPath, $context)) {
            throw new FileSystemException("remove directory failure, path: $dirPath");
        }
    }
}
