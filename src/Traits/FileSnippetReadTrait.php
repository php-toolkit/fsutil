<?php declare(strict_types=1);
/**
 * This file is part of toolkit/fsutil.
 *
 * @author   https://github.com/inhere
 * @link     https://github.com/toolkit/fsutil
 * @license  MIT
 */

namespace Toolkit\FsUtil\Traits;

use SplFileObject;
use Throwable;
use Toolkit\FsUtil\Exception\FileSystemException;
use function array_slice;
use function array_unshift;
use function assert;
use function count;
use function fclose;
use function feof;
use function fgets;
use function fopen;
use function fseek;
use function trim;

/**
 * Class FileSnippetReadTrait
 *
 * @package Toolkit\FsUtil
 */
trait FileSnippetReadTrait
{
    /**
     * @param string $file
     * @param bool   $filter
     *
     * @return array
     */
    public static function readAllLine(string $file, bool $filter = true): array
    {
        $contents = self::getContentsV2($file);

        if (!$contents) {
            return [];
        }

        $array = explode(PHP_EOL, $contents);

        return $filter ? array_filter($array) : $array;
    }

    /**
     * getLines 获取文件一定范围内的内容（支持大文件读取）
     *
     * @param string  $fileName  含完整路径的文件
     * @param integer $startLine 开始行数 默认第1行
     * @param integer $endLine   结束行数 默认第50行
     * @param string  $mode      打开文件方式
     *
     * @return array  返回内容
     * @throws FileSystemException
     */
    public static function readLines(string $fileName, int $startLine = 1, int $endLine = 10, string $mode = 'rb'): array
    {
        $content   = [];
        $startLine = $startLine <= 0 ? 1 : $startLine;

        if ($endLine <= $startLine) {
            return $content;
        }

        $count = $endLine - $startLine;

        try {
            $objFile = new SplFileObject($fileName, $mode);
            $objFile->seek($startLine - 1); // 转到第N行, seek方法参数从0开始计数

            for ($i = 0; $i <= $count; ++$i) {
                $content[] = $objFile->current(); // current()获取当前行内容
                $objFile->next(); // 下一行
            }
        } catch (Throwable $e) {
            throw new FileSystemException("Error on read the file '$fileName'. ERR: " . $e->getMessage());
        }

        return $content;
    }

    /**
     * symmetry  得到当前行对称上下几($lineNum)行的内容
     *
     * @param string  $filepath 含完整路径的文件
     * @param integer $current  [当前行数]
     * @param integer $lineNum  [获取行数] = $lineNum*2+1
     *
     * @return array
     * @throws FileSystemException
     */
    public static function readSymmetry(string $filepath, int $current = 1, int $lineNum = 3): array
    {
        $startLine = $current - $lineNum;
        $endLine   = $current + $lineNum;

        if ($current < ($lineNum + 1)) {
            $startLine = 1;
            $endLine   = 9;
        }

        return self::readLines($filepath, $startLine, $endLine);
    }

    /**
     * @param string $file
     * @param int    $baseLine
     * @param int    $prevLines
     * @param int    $nextLines
     *
     * @return array
     * @throws FileSystemException
     */
    public static function readRangeLines(string $file, int $baseLine, int $prevLines = 3, int $nextLines = 3): array
    {
        $startLine = $baseLine - $prevLines;
        $endLine   = $baseLine + $nextLines;

        return self::readLines($file, $startLine, $endLine);
    }

    /**
     * 得到基准行数上5行下3行的内容， lines up and down
     *
     * @param string $file
     * @param int    $baseLine 基准行数
     *
     * @return array
     * @throws FileSystemException
     */
    public static function getLines5u3d(string $file, int $baseLine = 1): array
    {
        return self::readRangeLines($file, $baseLine, 5);
    }

    /**
     * Read the first line contents
     *
     * @param string $filepath
     *
     * @return string
     */
    public static function readFirstLine(string $filepath): string
    {
        $file = fopen($filepath, 'rb');
        $line = trim((string)fgets($file));
        fclose($file);

        return $line;
    }

    /**
     * 读取文件的最后几行（支持大文件读取）
     *
     * @link http://www.jb51.net/article/81909.htm
     *
     * @param resource $fp e.g fopen("access.log", "r+")
     * @param int      $n
     * @param int      $base
     *
     * @return array
     */
    public static function tail($fp, int $n, int $base = 5): array
    {
        assert($n > 0);

        $pos   = $n + 1;
        $lines = [];

        while (count($lines) <= $n) {
            try {
                fseek($fp, -$pos, SEEK_END);
            } catch (Throwable $e) {
                fclose($fp);
                break;
            }

            $pos *= $base;

            while (!feof($fp)) {
                array_unshift($lines, fgets($fp));
            }
        }

        return array_slice($lines, 0, $n);
    }
}
