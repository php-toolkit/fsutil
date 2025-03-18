<?php declare(strict_types=1);

namespace Toolkit\FsUtilTest;

use PHPUnit\Framework\TestCase;
use Toolkit\FsUtil\Path;

/**
 * class PathTest
 *
 * @author inhere
 */
class PathTest extends TestCase
{
    public function testPath_isAbs(): void
    {
        $tests = [
            '/tmp',
            'C:/tmp',
            'C:\\tmp',
            'C:\tmp',
            "C:\\tmp",
        ];

        foreach ($tests as $case) {
            $this->assertTrue(Path::isAbs($case));
            $this->assertTrue(Path::isAbsolute($case));
        }
    }

    public function testFormat(): void
    {
        $tests = [
            '/path/to/tmp' => '/path/to/tmp',
            'C:\tmp' => 'C:/tmp',
            'C:/path/to/dir' => 'C:/path/to/dir',
            'C:\\path\\to\\dir' => 'C:/path/to/dir',
            "path\\to\\dir" => 'path/to/dir',
        ];

        foreach ($tests as $case => $expect) {
            $this->assertSame($expect, Path::format($case, false));
            $this->assertSame($expect . '/', Path::format($case));
        }
    }
}
