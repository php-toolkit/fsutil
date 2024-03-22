<?php declare(strict_types=1);

namespace Toolkit\FsUtil\Extra;

use Toolkit\FsUtil\Dir;
use Toolkit\FsUtil\File;
use Toolkit\FsUtil\FS;
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
     * base workdir, only init on first set workdir.
     *
     * @var string
     */
    private string $baseDir = '';

    /**
     * @var string The previous dir path on change workdir.
     */
    private string $prevDir = '';

    /**
     * @var string The workdir path for build files/dirs.
     */
    private string $workdir = '';

    /**
     * @var array<string, mixed> The template vars for render file content.
     */
    public array $tplVars = [];

    /**
     * @var string The template dir path
     */
    public string $tplDir = '';

    /**
     * Custom render file function
     *
     * ## Usage
     *
     * ```php
     * $ftb = FileTreeBuilder::new();
     * $ftb->setRenderFn(function (string $tplFile, array $tplVars): string {
     *     $content = $yourTplEng->renderFile($tplFile, $tplVars); // custom render content
     *     return $content;
     * });
     * ```
     *
     * @var null|callable(string, array):string
     * @see FileTreeBuilder::doReplace()
     */
    private $renderFn = null;

    /**
     * Callable on after file copied.
     *
     * @var callable(string $newFile): void
     */
    public $afterCopy;

    /**
     * set workdir path
     *
     * @param string $dir
     *
     * @return $this
     */
    public function workdir(string $dir): static
    {
        return $this->setWorkdir($dir);
    }

    //
    // ------------------------- copy file/dir -------------------------
    //

    /**
     * @param string        $srcFile source file path.
     * @param string        $dstFile dst file path, default relative the workDir.
     * @param callable|null $afterFn
     *
     * @return $this
     */
    public function copy(string $srcFile, string $dstFile, ?callable $afterFn = null): static
    {
        $dstFile = $this->getRealpath($dstFile);
        $this->printMsg("copy file $srcFile to $dstFile");
        if (!$this->dryRun) {
            $srcFile = $this->getRealpath($srcFile);
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
     * @param string $srcDir source dir path. default relative the workDir.
     * @param string $dstDir dst dir path, default relative the workDir.
     * @param array  $options = [
     *      'include'  => [], // limit copy files or dirs
     *      'exclude'  => [], // exclude files or dirs
     *      'renderOn' => ['*.java', ], // patterns to render
     *      'afterFn' => function(string $newFile) {},
     * ]
     *
     * @return $this
     */
    public function copyDir(string $srcDir, string $dstDir, array $options = []): static
    {
        $options = array_merge([
            'include'  => [],
            'exclude'  => [],
            'renderOn' => [], // patterns to render
            'afterFn'  => null,
        ], $options);

        $srcDir = $this->getRealpath($srcDir);
        $dstDir = $this->getRealpath($dstDir);
        $this->printMsg("copy dir: $srcDir -> $dstDir");

        Dir::copy($srcDir, $dstDir, [
            'filterFn' => function (string $oldFile) use ($options): bool {
                if ($options['include']) {
                    return File::isInclude($oldFile, $options['include']);
                }
                return !File::isExclude($oldFile, $options['exclude']);
            },
            'beforeFn' => function (string $oldFile, string $newFile): bool {
                $this->printMsgf('- copy file %s -> %s', $oldFile, $newFile);
                return !$this->dryRun;
            },
            'afterFn'  => function (string $newFile) use ($options) {
                if ($patterns = $options['renderOn']) {
                    $this->renderOnMatch($newFile, $patterns);
                }
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

    //
    // ------------------------- create file/dir -------------------------
    //

    /**
     * create new file with contents.
     *
     * @param string $name file path, default relative the workDir.
     * @param string $contents
     *
     * @return $this
     */
    public function file(string $name, string $contents = ''): static
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
     * @param array  $files file paths, default relative the workDir.
     * @param string $contents
     *
     * @return $this
     */
    public function files(array $files, string $contents = ''): static
    {
        foreach ($files as $file) {
            $this->file($file, $contents);
        }
        return $this;
    }

    /**
     * Quick make a dir
     *
     * ### $dir
     *
     * - can use absolute path or relative path{@link FileTreeBuilder::$workdir}
     * - can use var from {@link FileTreeBuilder::$tplVars}
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
     * @param string                   $dir dir name or path.
     * @param callable(self):void|null $intoFn If not null, will change workdir and call it with self instance
     *
     * @return $this
     */
    public function dir(string $dir, callable $intoFn = null): static
    {
        Assert::notBlank($dir);
        $dirPath = $this->getRealpath($dir);
        if (!$this->dryRun) {
            Dir::mkdir($dirPath);
        }

        if ($intoFn) {
            $this->printMsgf("create dir: $dirPath, with into func");
            $ftb = clone $this;
            $ftb->changeWorkdir($dirPath);
            $intoFn($ftb);
        } else {
            $this->printMsgf("create dir: $dirPath");
        }

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
    public function dirFiles(string $name, string ...$files): static
    {
        return $this->dir($name, function (self $ftb) use ($files) {
            $ftb->files($files);
        });
    }

    /**
     * Quick make multi dir
     *
     * @param string ...$dirs
     *
     * @return $this
     */
    public function dirs(string ...$dirs): static
    {
        foreach ($dirs as $dir) {
            $this->dir($dir);
        }
        return $this;
    }

    /**
     * Into a dir and run $infoFn
     *
     * @param string        $dir
     * @param callable|null $intoFn
     *
     * @return $this
     */
    public function into(string $dir, callable $intoFn = null): static
    {
        Assert::notBlank($dir);
        $dirPath = $this->getRealpath($dir);

        $this->printMsgf("into dir $dirPath, with func");
        $ftb = clone $this;
        $ftb->changeWorkdir($dirPath);
        $intoFn($ftb);

        return $this;
    }

    //
    // ------------------------- render vars -------------------------
    //

    /**
     * Simple render template by replace template vars.
     *
     * - not support expression on template.
     * - template var format: `{{var}}`
     *
     * @param string $tplFile
     * @param array  $tplVars
     *
     * @return $this
     */
    public function fastRender(string $tplFile, array $tplVars = []): static
    {
        Assert::notBlank($tplFile);
        $tplFile = $this->getRealpath($tplFile);

        $this->printMsgf('replace vars: %s', $tplFile);
        $this->doFastRender($tplFile, $tplFile, $tplVars);

        return $this;
    }

    /**
     * simple render template by replace template vars. format: `{{var}}`
     *
     * @param string $tplFile
     * @param string $dstFile
     * @param array  $tplVars
     *
     * @return void
     */
    protected function doFastRender(string $tplFile, string $dstFile, array $tplVars = []): void
    {
        if ($this->dryRun) {
            $this->printMsgf('skip render: %s', $tplFile);
            return;
        }

        if ($this->tplVars) {
            $tplVars = array_merge($this->tplVars, $tplVars);
        }

        $content = Str::renderTemplate(File::readAll($tplFile), $tplVars);
        File::putContents($dstFile, $content);
    }

    /**
     * Render template files by glob match.
     *
     * @param string $pattern
     * @param array  $tplVars
     *
     * @return $this
     */
    public function renderByGlob(string $pattern, array $tplVars = []): static
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
     * @param array  $patterns
     * @param array  $tplVars
     *
     * @return $this
     */
    public function renderOnMatch(string $tplFile, array $patterns, array $tplVars = []): static
    {
        if (File::isInclude($tplFile, $patterns)) {
            $this->tplFile($tplFile, '', $tplVars);
        }
        return $this;
    }

    /**
     * @param string|array $tplFiles
     * @param array        $tplVars
     *
     * @return $this
     */
    public function renderExists(string|array $tplFiles, array $tplVars = []): static
    {
        foreach ((array)$tplFiles as $key => $tplFile) {
            $dstFile = '';
            if ($key && is_string($key)) {
                $dstFile = $tplFile;
                $tplFile = $key;
            }
            $this->tplExists($tplFile, $dstFile, $tplVars);
        }

        return $this;
    }

    /**
     * Render template vars in the give file, will update file contents to rendered.
     *
     * @param string $tplFile
     * @param array  $tplVars
     *
     * @return $this
     */
    public function renderFile(string $tplFile, array $tplVars = []): static
    {
        return $this->tplFile($tplFile, '', $tplVars);
    }

    /**
     * Create files from template files
     *
     * @param array $tpl2dstMap
     * @param array $tplVars
     *
     * @return $this
     */
    public function tplFiles(array $tpl2dstMap, array $tplVars = []): static
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
     * Alias of {@see tplFile()} method.
     *
     * @param string $tplFile
     * @param string $dstFile
     * @param array  $tplVars
     *
     * @return $this
     */
    public function tpl(string $tplFile, string $dstFile = '', array $tplVars = []): static
    {
        return $this->tplFile($tplFile, $dstFile, $tplVars);
    }

    /**
     * Create file from a template file.
     *
     * - TIP: can use {pathVar} in the path, see {@see getRealpath()}
     *
     * @param string $tplFile tpl file path, relative the tplDir.
     * @param string $dstFile Dst file path, relative the workdir. If empty, use $tplFile for update.
     * @param array  $tplVars
     *
     * @return $this
     */
    public function tplFile(string $tplFile, string $dstFile = '', array $tplVars = []): static
    {
        Assert::notBlank($tplFile);
        $tplFile = $this->getRealpath($tplFile);

        if ($dstFile) {
            $dstFile = $this->getRealpath($dstFile);
            $this->printMsgf('render file: %s -> %s', $tplFile, $dstFile);
        } else {
            $dstFile = $tplFile;
            $this->printMsgf('render file: %s', $tplFile);
        }

        return $this->doRender($tplFile, $dstFile, $tplVars);
    }

    /**
     * Create file on the template file exists.
     *
     * @param string $tplFile
     * @param string $dstFile
     * @param array  $tplVars
     *
     * @return $this
     */
    public function tplExists(string $tplFile, string $dstFile = '', array $tplVars = []): self
    {
        $tplFile = $this->getRealpath($tplFile);
        if (!File::exists($tplFile)) {
            return $this;
        }

        return $this->tplFile($tplFile, $dstFile, $tplVars);
    }

    /**
     * Render tplFile file to dstFile, then remove source file.
     *
     * @return $this
     */
    public function replace(string $tplFile, string $dstFile, array $tplVars = []): static
    {
        $tplFile = $this->getRealpath($tplFile);
        $this->tplFile($tplFile, $dstFile, $tplVars);

        $this->printMsgf('remove file: %s', $tplFile);
        return $this->remove($tplFile);
    }

    /**
     * Do render template file with vars
     *
     * - should support expression on template.
     * - TIP: recommended use package: phppkg/easytpl#EasyTemplate
     *
     * @param string $tplFile
     * @param string $dstFile
     * @param array  $tplVars
     *
     * @return $this
     */
    protected function doRender(string $tplFile, string $dstFile, array $tplVars = []): self
    {
        if ($this->dryRun) {
            $this->printMsgf('skip render: %s', $tplFile);
            return $this;
        }

        // custom render function.
        if ($renderFn = $this->renderFn) {
            if ($this->tplVars) {
                $tplVars = array_merge($this->tplVars, $tplVars);
            }

            $content = $renderFn($tplFile, $tplVars);
            File::putContents($dstFile, $content);
            return $this;
        }

        // fallback use var replacer
        $this->doFastRender($tplFile, $dstFile, $tplVars);
        return $this;
    }

    /**
     * @param string ...$paths
     *
     * @return $this
     */
    public function remove(string ...$paths): static
    {
        foreach ($paths as $path) {
            FS::removePath($this->getRealpath($path));
        }
        return $this;
    }

    //
    // ------------------------- helper methods -------------------------
    //

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
     * @param string $path path, will apply {@see renderPathVars()}
     *
     * @return string
     */
    public function getRealpath(string $path, string $baseDir = ''): string
    {
        $realPath = $this->renderPathVars($path);
        if ($path && Dir::isRelative($realPath)) {
            $realPath = Dir::join($baseDir ?: $this->workdir, $realPath);
        }
        return $realPath;
    }

    /**
     * @param string $path eg: {workdir}/ab/c
     *
     * @return string
     */
    protected function renderPathVars(string $path): string
    {
        $vars = array_merge($this->tplVars, [
            'baseDir' => $this->baseDir,
            'tplDir'  => $this->tplDir,
            'prevDir' => $this->prevDir,
            'current' => $this->workdir,
            'workdir' => $this->workdir,
        ]);

        return Str::renderVars($path, $vars, '{%s}', true);
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
            if ($name[0] === '.' || Dir::isRelative($name)) {
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

            $pTplDir = dirname($this->tplDir);
            println(str_replace([ $pTplDir, $this->baseDir], ['TPL_DIR/..', 'PROJECT_DIR'], $msg));
        }
    }

    /**
     * @param string $tpl
     * @param mixed  ...$vars
     *
     * @return void
     */
    protected function printMsgf(string $tpl, ...$vars): void
    {
        $this->printMsg(sprintf($tpl, ...$vars));
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

    /**
     * @return callable|null
     */
    public function getRenderFn(): ?callable
    {
        return $this->renderFn;
    }

    /**
     * @param callable(string, array):string $renderFn
     *
     * @return $this
     */
    public function setRenderFn(callable $renderFn): self
    {
        $this->renderFn = $renderFn;
        return $this;
    }

}
