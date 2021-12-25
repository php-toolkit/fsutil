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
}
