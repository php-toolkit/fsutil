<?php declare(strict_types=1);

namespace Toolkit\FsUtilTest;

use PHPUnit\Framework\TestCase;
use Toolkit\FsUtil\FS;

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
        }
    }

    public function testClearPharPath(): void
    {
        $tests = [
            ['phar://E:/workenv/xxx/yyy/app.phar/web', 'E:/workenv/xxx/yyy/web'],
            ['phar:///workenv/xxx/yyy/app.phar/web', '/workenv/xxx/yyy/web'],
            ['E:/workenv/xxx/yyy/web', 'E:/workenv/xxx/yyy/web'],
            ['/workenv/xxx/yyy/web', '/workenv/xxx/yyy/web'],
        ];

        foreach ($tests as [$test, $want]) {
            $this->assertSame($want, FS::clearPharPath($test));
        }
    }
}
