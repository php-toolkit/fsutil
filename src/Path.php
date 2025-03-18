<?php declare(strict_types=1);

namespace Toolkit\FsUtil;

/**
 * Class Path
 *
 * @package Toolkit\FsUtil
 */
class Path extends FileSystem
{
    /**
     * @param string $path
     *
     * @return bool
     */
    public static function isAbs(string $path): bool
    {
        return self::isAbsPath($path);
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public static function isAbsolute(string $path): bool
    {
        return self::isAbsolutePath($path);
    }

    /**
     * @param string $path
     * @param bool   $endWithSlash
     *
     * @return string
     */
    public static function format(string $path, bool $endWithSlash = true): string
    {
        return self::pathFormat($path, $endWithSlash);
    }
}
