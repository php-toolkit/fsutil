<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/6/17
 * Time: 上午11:41
 */

namespace Toolkit\FsUtil\Parser;

use InvalidArgumentException;
use UnexpectedValueException;
use function array_merge;
use function file_exists;
use function file_get_contents;
use function is_callable;
use function is_file;
use function is_string;
use function parse_ini_string;
use function strpos;
use function substr;
use function trim;

/**
 * Class IniParser
 *
 * @package Toolkit\FsUtil\Parser
 */
class IniParser extends BaseParser
{
    /**
     * parse INI
     *
     * @param string   $string      Waiting for the parse data
     * @param bool     $enhancement 启用增强功能，支持通过关键字 继承、导入、参考
     * @param callable $pathHandler When the second param is true, this param is valid.
     * @param string   $fileDir     When the second param is true, this param is valid.
     *
     * @return array
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    protected static function doParse(
        $string,
        $enhancement = false,
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
        $array = parse_ini_string(trim($string), true);

        /*
         * Parse special keywords
         *
         * extend = ../parent.ini
         * db = import#../db.ini
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
                    $array = array_merge(parse_ini_string(trim($data), true), $array);
                } else {
                    throw new UnexpectedValueException("needed extended file [$extendFile] don't exists!");
                }
            }

            foreach ($array as $key => $item) {
                if (!is_string($item)) {
                    continue;
                }

                if (0 === strpos($item, self::IMPORT_KEY . '#')) {
                    $importFile = trim(substr($item, 7));

                    // if needed custom handle $importFile path. e.g: Maybe it uses custom alias path
                    if ($pathHandler && is_callable($pathHandler)) {
                        $importFile = $pathHandler($importFile);
                    }

                    // if $importFile is not exists AND $importFile is not a absolute path AND have $parentFile
                    if ($fileDir && !file_exists($importFile) && $importFile[0] !== '/') {
                        $importFile = $fileDir . '/' . trim($importFile, './');
                    }

                    // $importFile is file
                    if (is_file($importFile)) {
                        $data        = file_get_contents($importFile);
                        $array[$key] = parse_ini_string(trim($data), true);
                    } else {
                        throw new UnexpectedValueException("needed imported file [$importFile] don't exists!");
                    }
                }
            }

        }

        return $array;
    }

}
