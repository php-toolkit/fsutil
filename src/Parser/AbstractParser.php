<?php declare(strict_types=1);
/**
 * This file is part of toolkit/fsutil.
 *
 * @author   https://github.com/inhere
 * @link     https://github.com/toolkit/fsutil
 * @license  MIT
 */

namespace Toolkit\FsUtil\Parser;

use InvalidArgumentException;
use function dirname;
use function file_get_contents;
use function is_file;
use function strlen;

/**
 * Class BaseParser
 *
 * @package Toolkit\FsUtil\Parser
 */
abstract class AbstractParser
{
    public const EXTEND_KEY    = 'extend';

    public const IMPORT_KEY    = 'import';

    public const REFERENCE_KEY = 'reference';

    /**
     * parse data
     *
     * @param string   $string      Waiting for the parse data
     * @param bool     $enhancement 启用增强功能，支持通过关键字 继承、导入、参考
     * @param callable|null $pathHandler When the second param is true, this param is valid.
     * @param string   $fileDir     When the second param is true, this param is valid.
     *
     * @return array
     */
    abstract protected static function doParse(
        string $string,
        bool $enhancement = false,
        callable $pathHandler = null,
        string $fileDir = ''
    ): array;

    /**
     * @param string        $string
     * @param bool          $enhancement
     * @param callable|null $pathHandler
     * @param string        $fileDir
     *
     * @return array
     */
    public static function parse(
        string $string,
        bool $enhancement = false,
        callable $pathHandler = null,
        string $fileDir = ''
    ): array {
        if (strlen($string) < 256 && is_file($string)) {
            return self::parseFile($string, $enhancement, $pathHandler, $fileDir);
        }

        return static::doParse($string, $enhancement, $pathHandler, $fileDir);
    }

    /**
     * @param string        $file
     * @param bool          $enhancement
     * @param callable|null $pathHandler
     * @param string        $fileDir
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public static function parseFile(
        string $file,
        bool $enhancement = false,
        callable $pathHandler = null,
        string $fileDir = ''
    ): array {
        if (!is_file($file)) {
            throw new InvalidArgumentException("Target file [$file] not exists");
        }

        $fileDir  = $fileDir ?: dirname($file);
        $contents = file_get_contents($file);

        return static::doParse($contents, $enhancement, $pathHandler, $fileDir);
    }

    /**
     * @param string      $string
     * @param bool          $enhancement
     * @param callable|null $pathHandler
     * @param string        $fileDir
     *
     * @return array
     */
    public static function parseString(
        string $string,
        bool $enhancement = false,
        callable $pathHandler = null,
        string $fileDir = ''
    ): array {
        return static::doParse($string, $enhancement, $pathHandler, $fileDir);
    }

    /**
     * @param string        $value
     * @param string        $fileDir
     * @param callable|null $pathHandler
     *
     * @return string
     */
    protected static function getImportFile(string $value, string $fileDir, callable $pathHandler = null): string
    {
        // eg: 'import#other.yaml'
        $importFile = trim(substr($value, 7));

        // if needed custom handle $importFile path. e.g: Maybe it uses custom alias path
        if ($pathHandler && is_callable($pathHandler)) {
            $importFile = $pathHandler($importFile);
        }

        // if $importFile is not exists AND $importFile is not a absolute path AND have $parentFile
        if ($fileDir && !file_exists($importFile) && $importFile[0] !== '/') {
            $importFile = $fileDir . '/' . trim($importFile, './');
        }

        return $importFile;
    }
}
