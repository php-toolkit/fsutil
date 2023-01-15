<?php declare(strict_types=1);

namespace Toolkit\FsUtil\Extra;

use Toolkit\FsUtil\Dir;
use Toolkit\FsUtil\File;
use Toolkit\Stdlib\Helper\Assert;
use Toolkit\Stdlib\Obj\AbstractObj;
use Toolkit\Stdlib\Str;
use function array_merge;
use function is_int;
use function println;
use function str_replace;
use function trim;

/**
 * class FileTreeMaker
 *
 * @author inhere
 * @date 2022/12/26
 */
class FileTreeBuilder extends AbstractObj
{
    /**
     * @var bool
     */
    public bool $showMsg = false;

    /**
     * @var bool
     */
    public bool $dryRun = false;

    /**
     * @var string
     */
    private string $prevDir = '';

    /**
     * @var string
     */
    private string $workdir = '';

    /**
     * base workdir, only init on first set workdir.
     *
     * @var string
     */
    private string $baseDir = '';

    /**
     * @var array<string, mixed>
     */
    public array $tplVars = [];

    /**
     * @var string
     */
    public string $tplDir = '';

    /**
     * Callable on after file copied.
     *
     * @var callable(string $newFile): void
     */
    public $afterCopy;

    /**
     * @param string $dir
     *
     * @return $this
     */
    public function workdir(string $dir): self
    {
        return $this->setWorkdir($dir);
    }

    /**
     * @param string $srcFile source file path.
     * @param string $dstFile dst file path, default relative the workDir.
     * @param callable|null $afterFn
     *
     * @return $this
     */
    public function copy(string $srcFile, string $dstFile, ?callable $afterFn = null): self
    {
        $this->printMsg("copy file $srcFile to $dstFile");
        if (!$this->dryRun) {
            File::copyFile($srcFile, $dstFile);
        }

        if ($afterFn !== null) {
            $afterFn($dstFile);
        }

        if ($fn = $this->afterCopy) {
            $fn($dstFile);
        }

        return $this;
    }

    /**
     * Copy all files in dir to dst dir
     *
     * ### Exclude files:
     *
     * ```php
     *  $ftb->copyDir('path/to/template dir', './', [
     *      'exclude'  => ['*.tpl'],
     *  ])
     * ```
     *
     * ### Adv Usage:
     *
     * ```php
     *  $ftb->copyDir('path/to/template dir', './', [
     *      'afterFn' => function (string $newFile) use ($ftb) {
     *          // render vars in the match file
     *          $ftb->renderOnMatch($newFile, ['*.java']);
     *      },
     *  ])
     * ```
     *
     * @param string $srcDir source dir path.
     * @param string $dstDir dst dir path, default relative the workDir.
     * @param array $options = [
     *      'include'  => [], // limit copy files
     *      'exclude'  => [], // can exclude files on copy
     *      'afterFn' => function(string $newFile) {},
     * ]
     *
     * @return $this
     */
    public function copyDir(string $srcDir, string $dstDir, array $options = []): self
    {
        $options = array_merge([
            'include' => [],
            'exclude' => [],
            'afterFn' => null,
        ], $options);

        $dstDir = $this->getRealpath($dstDir);
        $this->printMsg("copy dir $srcDir to $dstDir");

        Dir::copy($srcDir, $dstDir, [
            'filterFn' => function (string $oldFile) use ($options): bool {
                if ($options['include']) {
                    return File::isInclude($oldFile, $options['include']);
                }
                return !File::isExclude($oldFile, $options['exclude']);
            },
            'beforeFn' => function (string $oldFile, string $newFile): bool {
                $this->printMsgf('- copy file %s to %s', $oldFile, $newFile);

                return !$this->dryRun;
            },
            'afterFn'  => function (string $newFile) use ($options) {
                if ($fn = $options['afterFn']) {
                    $fn($newFile);
                }

                if ($fn = $this->afterCopy) {
                    $fn($newFile);
                }
            },
        ]);

        return $this;
    }

    /**
     * create new file
     *
     * @param string $name file path, default relative the workDir.
     * @param string $contents
     *
     * @return $this
     */
    public function file(string $name, string $contents = ''): self
    {
        Assert::notBlank($name);
        $filePath = $this->getRealpath($name);
        $this->printMsg("create file: $filePath");

        if (!$this->dryRun) {
            File::putContents($filePath, $contents);
        }
        return $this;
    }

    /**
     * Create multi files at once.
     *
     * @param array $files file paths, default relative the workDir.
     * @param string $contents
     *
     * @return $this
     */
    public function files(array $files, string $contents = ''): self
    {
        foreach ($files as $file) {
            $this->file($file, $contents);
        }
        return $this;
    }

    /**
     * Quick make a dir
     *
     * ### Usage for $intoFn:
     *
     * ```php
     *  $ftb->dir($dir, function (FileTreeBuilder $ftb) {
     *      // workdir will change to $dir
     *      $ftb->file('some.txt')
     *          ->dir('sub-dir');
     *  })
     * ```
     *
     * @param string $dir dir name or path.
     * @param callable|null $intoFn
     *
     * @return $this
     */
    public function dir(string $dir, callable $intoFn = null): self
    {
        Assert::notBlank($dir);
        $dirPath = $this->getRealpath($dir);

        if (!$this->dryRun) {
            Dir::mkdir($dirPath);
        }

        if ($intoFn !== null) {
            $this->printMsgf("make dir: $dirPath, with into func");
            $ftb = clone $this;
            $ftb->changeWorkdir($dirPath);
            $intoFn($ftb);
        } else {
            $this->printMsgf("make dir: $dirPath");
        }

        return $this;
    }

    /**
     * Quick make multi dir
     *
     * @param string ...$dirs
     *
     * @return $this
     */
    public function dirs(string ...$dirs): self
    {
        foreach ($dirs as $dir) {
            $this->dir($dir);
        }
        return $this;
    }

    /**
     * Into a dir and run $infoFn
     *
     * @param string $dir
     * @param callable|null $intoFn
     *
     * @return $this
     */
    public function into(string $dir, callable $intoFn = null): self
    {
        Assert::notBlank($dir);
        $dirPath = $this->getRealpath($dir);

        $this->printMsgf("into dir $dirPath, with func");
        $ftb = clone $this;
        $ftb->changeWorkdir($dirPath);
        $intoFn($ftb);

        return $this;
    }

    /**
     * Quick make a dir and multi files
     *
     * @param string $name
     * @param string ...$files
     *
     * @return $this
     */
    public function dirFiles(string $name, string ...$files): self
    {
        return $this->dir($name, function (self $ftb) use ($files) {
            $ftb->files($files);
        });
    }

    /**
     * Simple render template by replace template vars.
     *
     * - not support expression on template.
     *
     * @param string $tplFile
     * @param array $tplVars
     *
     * @return $this
     */
    public function replaceVars(string $tplFile, array $tplVars = []): static
    {
        Assert::notBlank($tplFile);

        $dstFile = $this->getRealpath($tplFile);
        if (!File::isAbsPath($tplFile)) {
            $tplFile = $this->tplDir . '/' . $tplFile;
        }

        $this->printMsgf('replace vars: %s', $tplFile);
        $this->doReplace($tplFile, $dstFile, $tplVars);

        return $this;
    }

    /**
     * @param string $tplFile
     * @param string $dstFile
     * @param array $tplVars
     *
     * @return void
     */
    protected function doReplace(string $tplFile, string $dstFile, array $tplVars = []): void
    {
        if (!$this->dryRun) {
            if ($this->tplVars) {
                $tplVars = array_merge($this->tplVars, $tplVars);
            }

            $content = Str::renderTemplate(File::readAll($tplFile), $tplVars);
            File::putContents($dstFile, $content);
        }
    }

    /**
     * Render template files by glob match.
     *
     * @param string $pattern
     * @param array $tplVars
     *
     * @return $this
     */
    public function renderByGlob(string $pattern, array $tplVars = []): self
    {
        foreach (glob($pattern) as $tplFile) {
            $this->tplFile($tplFile, '', $tplVars);
        }
        return $this;
    }

    /**
     * Render give file on match patterns.
     *
     * @param string $tplFile
     * @param array $patterns
     * @param array $tplVars
     *
     * @return $this
     */
    public function renderOnMatch(string $tplFile, array $patterns, array $tplVars = []): self
    {
        if (File::isInclude($tplFile, $patterns)) {
            $this->tplFile($tplFile, '', $tplVars);
        }
        return $this;
    }

    /**
     * Render template vars in the give file, will update file contents to rendered.
     *
     * @param string $tplFile
     * @param array $tplVars
     *
     * @return $this
     */
    public function renderFile(string $tplFile, array $tplVars = []): self
    {
        return $this->tplFile($tplFile, '', $tplVars);
    }

    /**
     * Create file from a template file
     *
     * @param string $tplFile tpl file path, relative the tplDir.
     * @param string $dstFile Dst file path, relative the workdir. If empty, use $tplFile for update.
     * @param array $tplVars
     *
     * @return $this
     */
    public function tplFile(string $tplFile, string $dstFile = '', array $tplVars = []): self
    {
        Assert::notBlank($tplFile);
        $dstFile = $this->getRealpath($dstFile ?: $tplFile);

        if (!File::isAbsPath($tplFile)) {
            $tplFile = $this->tplDir . '/' . $tplFile;
        }

        $this->printMsgf('render file: %s', $tplFile);

        return $this->doRender($tplFile, $dstFile, $tplVars);
    }

    /**
     * Do render template file with vars
     *
     * - should support expression on template.
     * - TIP: recommended use package: phppkg/easytpl#EasyTemplate
     *
     * @param string $tplFile
     * @param string $dstFile
     * @param array $tplVars
     *
     * @return $this
     */
    protected function doRender(string $tplFile, string $dstFile, array $tplVars = []): self
    {
        $this->doReplace($tplFile, $dstFile, $tplVars);

        return $this;
    }

    /**
     * Create files from template files
     *
     * @param array $tpl2dstMap
     * @param array $tplVars
     *
     * @return $this
     */
    public function tplFiles(array $tpl2dstMap, array $tplVars = []): self
    {
        foreach ($tpl2dstMap as $tplFile => $dstFile) {
            if (!$tplFile || is_int($tplFile)) {
                $tplFile = $dstFile;
            }

            $this->tplFile($tplFile, $dstFile, $tplVars);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function backPrev(): self
    {
        if ($this->prevDir) {
            $this->workdir = $this->prevDir;
            $this->prevDir = '';
        }
        return $this;
    }

    /**
     * get realpath relative the workdir
     *
     * @param string $path
     *
     * @return string
     */
    public function getRealpath(string $path): string
    {
        $realPath = $path;
        if ($path && Dir::isRelative($path)) {
            $realPath = Dir::join($this->workdir, $path);
        }

        return $realPath;
    }

    /**
     * @param string $name
     *
     * @return void
     */
    private function changeWorkdir(string $name): void
    {
        if ($name) {
            $this->prevDir = $this->workdir;
            // is relative path
            if ($name[0] === '.' || Str::isAlphaNum($name[0])) {
                $this->workdir .= '/' . trim($name, './');
            } else {
                $this->workdir = $name;
            }
        }
    }

    /**
     * @return string
     */
    public function getWorkdir(): string
    {
        return $this->workdir;
    }

    /**
     * @param string $workdir
     *
     * @return FileTreeBuilder
     */
    public function setWorkdir(string $workdir): self
    {
        $this->workdir = $workdir;

        if (!$this->baseDir) {
            $this->baseDir = $workdir;
        }
        return $this;
    }

    /**
     * @param bool $showMsg
     *
     * @return FileTreeBuilder
     */
    public function setShowMsg(bool $showMsg): self
    {
        $this->showMsg = $showMsg;
        return $this;
    }

    /**
     * @param string $tplDir
     *
     * @return FileTreeBuilder
     */
    public function setTplDir(string $tplDir): self
    {
        $this->tplDir = $tplDir;
        return $this;
    }

    /**
     * @param bool $dryRun
     *
     * @return FileTreeBuilder
     */
    public function setDryRun(bool $dryRun): self
    {
        $this->dryRun = $dryRun;
        return $this;
    }

    /**
     * @param string $msg
     *
     * @return void
     */
    protected function printMsg(string $msg): void
    {
        if ($this->showMsg) {
            if ($this->dryRun) {
                $msg = '[DRY-RUN] ' . $msg;
            }

            println(str_replace($this->baseDir, '{projectDir}', $msg));
        }
    }

    /**
     * @param string $tpl
     * @param ...$vars
     *
     * @return void
     */
    protected function printMsgf(string $tpl, ...$vars): void
    {
        if ($this->showMsg) {
            if ($this->dryRun) {
                $tpl = '[DRY-RUN] ' . $tpl;
            }

            println(str_replace($this->baseDir, '{projectDir}', sprintf($tpl, ...$vars)));
        }
    }

    /**
     * @param array $tplVars
     *
     * @return FileTreeBuilder
     */
    public function setTplVars(array $tplVars): self
    {
        $this->tplVars = $tplVars;
        return $this;
    }

    /**
     * @param callable(string $newFile): void $afterCopy
     *
     * @return FileTreeBuilder
     */
    public function setAfterCopy(callable $afterCopy): self
    {
        $this->afterCopy = $afterCopy;
        return $this;
    }

}
