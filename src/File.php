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
use Toolkit\FsUtil\Exception\FileReadException;
use Toolkit\FsUtil\Exception\FileWriteException;
use Toolkit\FsUtil\Exception\IOException;
use Toolkit\FsUtil\Parser\IniParser;
use Toolkit\FsUtil\Parser\JsonParser;
use Toolkit\FsUtil\Parser\YamlParser;
use Toolkit\FsUtil\Traits\FileOperateTrait;
use Toolkit\FsUtil\Traits\FileSnippetReadTrait;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function function_exists;
use function in_array;
use function is_array;
use function is_string;
use function strlen;

/**
 * Class File
 *
 * @package Toolkit\FsUtil
 */
class File extends FileSystem
{
    use FileOperateTrait;
    use FileSnippetReadTrait;

    public const FORMAT_PHP = 'php';

    public const FORMAT_JSON = 'json';

    public const FORMAT_INI = 'ini';

    public const FORMAT_YML = 'yml';

    public const FORMAT_YAML = 'yaml';

    /**********************************************************************************
     * config file load
     *********************************************************************************/

    /**
     * @param string $src 要解析的 文件 或 字符串内容。
     * @param string $format
     *
     * @return array
     * @throws FileNotFoundException
     */
    public static function load(string $src, string $format = self::FORMAT_PHP): array
    {
        $src = trim($src);
        switch ($format) {
            case self::FORMAT_YML:
            case self::FORMAT_YAML:
                $array = self::loadYaml($src);
                break;

            case self::FORMAT_JSON:
                $array = self::loadJson($src);
                break;

            case self::FORMAT_INI:
                $array = self::loadIni($src);
                break;

            case self::FORMAT_PHP:
            default:
                $array = self::loadPhp($src);
                break;
        }

        return $array;
    }

    /**
     * load array data form file.
     *
     * @param string $file
     * @param bool   $throwError
     *
     * @return array
     * @throws FileNotFoundException
     */
    public static function loadPhp(string $file, bool $throwError = true): array
    {
        $ary = [];

        if (is_file($file)) {
            /** @noinspection PhpIncludeInspection */
            $ary = require $file;

            if (!is_array($ary)) {
                $ary = [];
            }
        } elseif ($throwError) {
            throw new FileNotFoundException("php file [$file] not exists.");
        }

        return $ary;
    }

    /**
     * @param string $file
     *
     * @return array
     */
    public static function loadJson(string $file): array
    {
        return JsonParser::parse($file);
    }

    /**
     * @param string $ini 要解析的 ini 文件名 或 字符串内容。
     *
     * @return array
     */
    public static function loadIni(string $ini): array
    {
        return IniParser::parse($ini);
    }

    /**
     * @param string $yml 要解析的 yml 文件名 或 字符串内容。
     *
     * @return array
     */
    public static function loadYaml(string $yml): array
    {
        return YamlParser::parse($yml);
    }

    /**********************************************************************************
     * php function wrapper, add error handle
     *********************************************************************************/

    /**
     * @param string   $filename
     * @param bool     $useIncludePath
     * @param resource $context
     * @param int      $offset
     * @param int|null $maxlen
     *
     * @return string
     */
    public static function getContents(
        string $filename,
        bool $useIncludePath = false,
        $context = null,
        int $offset = 0,
        int $maxlen = null
    ): string {
        $content = file_get_contents($filename, $useIncludePath, $context, $offset, $maxlen);
        if ($content === false) {
            throw new FileWriteException('read contents error from file: ' . $filename);
        }

        return $content;
    }

    /**
     * save content use file_put_contents()
     *
     * @param string $filename
     * @param mixed  $data string, array(仅一维数组) 或者是 stream  资源
     * @param int    $flags
     * @param null   $context
     *
     * @return int
     */
    public static function putContents(string $filename, $data, int $flags = 0, $context = null): int
    {
        $number = file_put_contents($filename, $data, $flags, $context);
        if ($number === false) {
            throw new FileWriteException('write contents error to file: ' . $filename);
        }

        return $number;
    }

    /**
     * save content
     *
     * @param string $filename
     * @param mixed  $data string, array(仅一维数组) 或者是 stream  资源
     * @param int    $flags
     * @param mixed  $context
     *
     * @return int
     */
    public static function save(string $filename, string $data, int $flags = 0, $context = null): int
    {
        return self::putContents($filename, $data, $flags, $context);
    }

    /**
     * @param $content
     * @param $path
     *
     * @throws IOException
     */
    public static function write($content, $path): void
    {
        $handler = static::openHandler($path);

        static::writeToFile($handler, $content);

        @fclose($handler);
    }

    /**
     * @param string $path
     *
     * @return resource
     * @throws IOException
     */
    public static function openHandler(string $path)
    {
        if (($handler = @fopen($path, 'wb')) === false) {
            throw new IOException('The file "' . $path . '" could not be opened for writing. Check if PHP has enough permissions.');
        }

        return $handler;
    }

    /**
     * Attempts to write $content to the file specified by $handler. $path is used for printing exceptions.
     *
     * @param resource $handler The resource to write to.
     * @param string   $content The content to write.
     * @param string   $path    The path to the file (for exception printing only).
     *
     * @throws IOException
     */
    public static function writeToFile($handler, string $content, string $path = ''): void
    {
        if (($result = @fwrite($handler, $content)) === false || ($result < strlen($content))) {
            throw new IOException('The file "' . $path . '" could not be written to. Check your disk space and file permissions.');
        }
    }

    /**
     * ********************** 创建多级目录和多个文件 **********************
     * 结合上两个函数
     *
     * @param array $fileData - 数组：要创建的多个文件名组成,含文件的完整路径
     * @param bool  $append   - 是否以追加的方式写入数据 默认false
     * @param int   $mode     =0777 - 权限，默认0775
     *                        eg: $fileData = array(
     *                        'file_name'   => 'content',
     *                        'case.html'   => 'content' ,
     *                        );
     **/
    public static function createAndWrite(array $fileData = [], bool $append = false, int $mode = 0664): void
    {
        foreach ($fileData as $file => $content) {
            //检查目录是否存在，不存在就先创建（多级）目录
            Directory::create(dirname($file), $mode);

            //$fileName = basename($file); //文件名

            //检查文件是否存在
            if (!is_file($file)) {
                file_put_contents($file, $content, LOCK_EX);
                @chmod($file, $mode);
            } elseif ($append) {
                file_put_contents($file, $content, FILE_APPEND | LOCK_EX);
                @chmod($file, $mode);
            }
        }
    }

    /**
     * @param string        $file a file path or url path
     * @param bool|false    $useIncludePath
     * @param null|resource $streamContext
     * @param int           $curlTimeout
     *
     * @return bool|string
     * @throws FileNotFoundException
     * @throws FileReadException
     */
    public static function getContentsV2(
        string $file,
        bool $useIncludePath = false,
        $streamContext = null,
        int $curlTimeout = 5
    ) {
        $isUrl = preg_match('/^https?:\/\//', $file);
        if (null === $streamContext && $isUrl) {
            $streamContext = @stream_context_create(['http' => ['timeout' => $curlTimeout]]);
        }

        if ($isUrl && in_array(ini_get('allow_url_fopen'), ['On', 'on', '1'], true)) {
            if (!file_exists($file)) {
                throw new FileNotFoundException("File [$file] don't exists!");
            }

            if (!is_readable($file)) {
                throw new FileReadException("File [$file] is not readable！");
            }

            return @file_get_contents($file, $useIncludePath, $streamContext);
        }

        // fetch remote content by url
        if (function_exists('curl_init')) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_URL, $file);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($curl, CURLOPT_TIMEOUT, $curlTimeout);
            //  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

            if (null !== $streamContext) {
                $opts = stream_context_get_options($streamContext);

                if (isset($opts['http']['method']) && strtolower($opts['http']['method']) === 'post') {
                    curl_setopt($curl, CURLOPT_POST, true);

                    if (isset($opts['http']['content'])) {
                        parse_str($opts['http']['content'], $post_data);
                        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
                    }
                }
            }

            $content = curl_exec($curl);
            curl_close($curl);

            return $content;
        }

        return false;
    }

    /**
     * @param $inFile
     * @param $outFile
     *
     * @return mixed
     * @throws InvalidArgumentException
     * @throws FileNotFoundException
     */
    public static function combine($inFile, $outFile)
    {
        self::check($inFile);

        $data = '';
        if (is_array($inFile)) {
            foreach ($inFile as $value) {
                if (is_file($value)) {
                    $data .= trim(file_get_contents($value));
                } else {
                    throw new FileNotFoundException('File: ' . $value . ' not exists!');
                }
            }
        }

        /*if (is_string($inFile) && is_file($value)) {
            $data .= trim( file_get_contents($inFile) );
        } else {
            Trigger::error('文件'.$value.'不存在！！');
        }*/

        $preg_arr = [
            '/\/\*.*?\*\/\s*/is',        // 去掉所有多行注释/* .... */
            '/\/\/.*?[\r\n]/is',        // 去掉所有单行注释//....
            '/(?!\w)\s*?(?!\w)/is'     // 去掉空白行
        ];

        $data = preg_replace($preg_arr, '', $data);
        // $outFile  = $outDir . Data::getRandStr(8) . '.' . $fileType;

        $fileData = [
            $outFile => $data
        ];

        self::createAndWrite($fileData);

        return $outFile;
    }

    /**
     * Removes whitespace from a PHP source string while preserving line numbers.
     *
     * @param string $source A PHP string
     *
     * @return string The PHP string with the whitespace removed
     */
    public static function stripPhpCode(string $source): string
    {
        if (!function_exists('token_get_all')) {
            return $source;
        }

        $output = '';
        foreach (token_get_all($source) as $token) {
            if (is_string($token)) {
                $output .= $token;
            } elseif (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $output .= str_repeat("\n", substr_count($token[1], "\n"));
            } elseif (T_WHITESPACE === $token[0]) {
                // reduce wide spaces
                $whitespace = preg_replace('{[ \t]+}', ' ', $token[1]);
                // normalize newlines to \n
                $whitespace = preg_replace('{(?:\r\n|\r|\n)}', "\n", $whitespace);
                // trim leading spaces
                $whitespace = preg_replace('{\n +}', "\n", $whitespace);
                // append
                $output .= $whitespace;
            } else {
                $output .= $token[1];
            }
        }

        return $output;
    }

    /**
     * If you want to download files from a linux server with
     * a filesize bigger than 2GB you can use the following
     *
     * @param string $file
     * @param string $as
     */
    public static function downBigFile($file, $as): void
    {
        header('Expires: Mon, 1 Apr 1974 05:00:00 GMT');
        header('Pragma: no-cache');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Description: File Download');
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . trim(shell_exec('stat -c%s "$file"')));
        header('Content-Disposition: attachment; filename="' . $as . '"');
        header('Content-Transfer-Encoding: binary');
        //@readfile( $file );

        flush();
        $fp = popen('tail -c ' . trim(shell_exec('stat -c%s "$file"')) . ' ' . $file . ' 2>&1', 'r');

        while (!feof($fp)) {
            // send the current file part to the browser
            print fread($fp, 1024);
            // flush the content to the browser
            flush();
        }

        fclose($fp);
    }
}
