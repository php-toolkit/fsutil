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
use Toolkit\Stdlib\Helper\JsonHelper;
use UnexpectedValueException;
use function array_merge;
use function file_exists;
use function file_get_contents;
use function is_callable;
use function is_file;
use function is_string;
use function strpos;
use function trim;

/**
 * Class JsonParser
 *
 * @package Toolkit\FsUtil\Parser
 */
class JsonParser extends AbstractParser
{
    public const JSON = 'json';

    /**
     * parse JSON
     *
     * @param string   $string      Waiting for the parse data
     * @param bool     $enhancement 启用增强功能，支持通过关键字 继承、导入、参考
     * @param callable $pathHandler When the second param is true, this param is valid.
     * @param string   $fileDir     When the second param is true, this param is valid.
     *
     * @return array
     * @throws InvalidArgumentException|\JsonException
     */
    protected static function doParse(
        string $string,
        bool $enhancement = false,
        callable $pathHandler = null,
        string $fileDir = ''
    ): array {
        if (!$string) {
            return [];
        }

        if (!is_string($string)) {
            throw new InvalidArgumentException('parameter type error! must is string.');
        }

        /** @var array $array */
        $array = JsonHelper::parse($string);

        /*
         * Parse special keywords
         *
         * extend = ../parent.json
         * db = import#../db.json
         * [cache]
         * debug = reference#debug
         */
        if ($enhancement === true) {
            if (isset($array[self::EXTEND_KEY]) && ($extendFile = $array[self::EXTEND_KEY])) {
                // if needed custom handle $importFile path. e.g: Maybe it uses custom alias path
                if ($pathHandler && is_callable($pathHandler)) {
                    $extendFile = $pathHandler($extendFile);
                }

                // if $importFile is not exists AND $importFile is not a absolute path AND have $parentFile
                if ($fileDir && !file_exists($extendFile) && $extendFile[0] !== '/') {
                    $extendFile = $fileDir . '/' . trim($extendFile, './');
                }

                // $importFile is file
                if (is_file($extendFile)) {
                    $data  = file_get_contents($extendFile);
                    $array = array_merge(JsonHelper::parse($data), $array);
                } else {
                    throw new UnexpectedValueException("needed extended file [$extendFile] don't exists!");
                }
            }

            foreach ($array as $key => $item) {
                if (!is_string($item)) {
                    continue;
                }

                // eg: db = import#../db.json
                if (str_starts_with($item, self::IMPORT_KEY . '#')) {
                    $importFile = self::getImportFile($item, $fileDir, $pathHandler);

                    // $importFile is file
                    if (is_file($importFile)) {
                        $json = file_get_contents($importFile);
                        // parse sub file
                        $array[$key] = JsonHelper::decode($json, true);
                    } else {
                        throw new UnexpectedValueException("needed imported file [$importFile] don't exists!");
                    }
                }
            }
        }

        return $array;
    }
}
