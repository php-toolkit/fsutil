<?php declare(strict_types=1);

namespace Toolkit\FsUtilTest;

use PHPUnit\Framework\TestCase;
use Toolkit\FsUtil\FS;
use function strlen;
use function vdump;

/**
 * class FsTest
 */
class FsTest extends TestCase
{
    public function testIsAbsPath(): void
    {
        $tests = [
            '/tmp',
            'C:/tmp',
            'C:\\tmp',
            'C:\tmp',
            "C:\\tmp",
        ];

        foreach ($tests as $case) {
            $this->assertTrue(FS::isAbsPath($case));
            $this->assertTrue(FS::isAbsolutePath($case));
            $this->assertFalse(FS::isRelative($case));
        }

        $this->assertTrue(FS::isRelative('./'));
        $this->assertTrue(FS::isRelative('./abc'));
    }

    public function testBasicFsMethods(): void
    {
        // join
        $this->assertEquals('/ab', FS::join('/ab'));
        $this->assertEquals('/ab/d', FS::join('/ab', '', 'd'));
        $this->assertEquals('/ab/d/e', FS::join('/ab', 'd', 'e'));
        $this->assertEquals('/ab', FS::join('/ab', '.'));
        $this->assertEquals('/ab', FS::join('/ab', './'));
        $this->assertEquals('/ab/cd', FS::join('/ab', './cd'));
    }

    public function testIsExclude_isInclude(): void
    {
        $this->assertTrue(FS::isInclude('./abc.php', []));
        $this->assertTrue(FS::isInclude('./abc.php', ['*']));
        $this->assertTrue(FS::isInclude('./abc.php', ['*.php']));
        $this->assertTrue(FS::isInclude('path/to/abc.php', ['*.php']));
        $this->assertFalse(FS::isInclude('./abc.php', ['*.xml']));

        $this->assertFalse(FS::isExclude('./abc.php', []));
        $this->assertTrue(FS::isExclude('./abc.php', ['*']));
        $this->assertTrue(FS::isExclude('./abc.php', ['*.php']));
        $this->assertTrue(FS::isExclude('path/to/abc.php', ['*.php']));
        $this->assertFalse(FS::isExclude('./abc.php', ['*.yml']));
    }

    public function testRealpath(): void
    {
        $rPaths = [];
        $tests = [
            '~',
            '~/.kite',
        ];
        foreach ($tests as $path) {
            $rPaths[$path] = $rPath = FS::realpath($path);
            $this->assertTrue(strlen($rPath) > strlen($path));
            $rPath = FS::getAbsPath($path);
            $this->assertTrue(strlen($rPath) > strlen($path));
        }

        vdump($rPaths);
    }

    public function testClearPharPath(): void
    {
        $tests = [
            ['phar://E:/workenv/xxx/yyy/app.phar/web', 'E:/workenv/xxx/yyy/web'],
            ['phar:///workenv/xxx/yyy/app.phar/web', '/workenv/xxx/yyy/web'],
            ['E:/workenv/xxx/yyy/web', 'E:/workenv/xxx/yyy/web'],
            ['/workenv/xxx/yyy/web', '/workenv/xxx/yyy/web'],
            ['phar:///home/runner/kite/kite-v1.1.8.phar/config/config.php', '/home/runner/kite/config/config.php'],
        ];

        foreach ($tests as [$test, $want]) {
            $this->assertSame($want, FS::clearPharPath($test));
        }
    }
}
