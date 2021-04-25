<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2018/3/8 0008
 * Time: 20:25
 */

namespace Toolkit\FsUtilTest;

use PHPUnit\Framework\TestCase;
use Toolkit\FsUtil\Parser\IniParser;

/**
 * Class IniParserTest
 *
 * @package Toolkit\FsUtil\ParserTest
 */
class IniParserTest extends TestCase
{
    /**
     * simple parse
     */
    public function testParse(): void
    {
        $ret = IniParser::parseFile(__DIR__ . '/data/test.ini');

        $this->assertArrayHasKey('name', $ret);
        $this->assertSame('import#include.ini', $ret['include']);


        $ret = IniParser::parseFile(__DIR__ . '/data/test.ini', true);

        $this->assertArrayHasKey('name', $ret);
        $this->assertArrayHasKey('he', $ret['include']);
    }
}
