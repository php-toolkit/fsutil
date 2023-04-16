<?php declare(strict_types=1);
/**
 * This file is part of toolkit/fsutil.
 *
 * @author   https://github.com/inhere
 * @link     https://github.com/toolkit/fsutil
 * @license  MIT
 */

namespace Toolkit\FsUtil\Extra;

use AppendIterator;
use ArrayIterator;
use Closure;
use Countable;
use Exception;
use FilterIterator;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Toolkit\Stdlib\Str;
use Traversable;
use UnexpectedValueException;
use function array_merge;
use function closedir;
use function count;
use function fnmatch;
use function get_object_vars;
use function is_iterable;
use function is_string;
use function iterator_count;
use function str_contains;
use function stream_get_meta_data;

/**
 * Class FileFinder
 *
 * ```php
 * $finder = FileFinder::create()
 *      ->files()
 *      ->name('*.php')
 *      ->notName('some.php')
 *      ->in('/path/to/project')
 * ;
 *
 * foreach($finder as $file) {
 *      // something ......
 * }
 * ```
 *
 * @package Toolkit\FsUtil
 * @ref \Symfony\Component\Finder\Finder
 */
class FileFinder implements IteratorAggregate, Countable
{
    public const MODE_ALL  = 0;
    public const ONLY_FILE = 1;
    public const ONLY_DIR  = 2;

    public const IGNORE_VCS_FILES = 1;
    public const IGNORE_DOT_FILES = 2;
    public const IGNORE_DOT_DIRS  = 4;

    public const MODE2DESC = [
        self::MODE_ALL  => 'ALL',
        self::ONLY_DIR  => 'DIR',
        self::ONLY_FILE => 'FILE',
    ];

    /** @var array */
    private static array $vcsPatterns = ['.svn', '_svn', 'CVS', '_darcs', '.arch-params', '.monotone', '.bzr', '.git', '.hg'];

    /** @var int */
    private int $mode = 0;

    /** @var int */
    private int $ignore;

    /** @var bool */
    private bool $initialized = false;

    /** @var bool recursive sub-dirs */
    private bool $recursive = true;

    /** @var bool */
    private bool $ignoreVcsAdded = false;

    /** @var bool */
    private bool $skipUnreadableDirs = true;

    /** @var array The find dirs */
    private array $dirs = [];

    /** @var array<string> exclude pattern for directory names and each sub-dirs */
    private array $excludes = [];

    /**
     * add include file,dir name match.
     *
     * eg: '.php' '*.php' 'test.php'
     *
     * @var array
     */
    private array $names = [];

    /**
     * add exclude file,dir name patterns, but sub-dir will not be exclude.
     *
     * eg: '.php' '*.php' 'test.php'
     *
     * @var array
     */
    private array $notNames = [];

    /** @var array<string> include paths pattern. */
    private array $paths = [];

    /** @var array<string> exclude paths pattern */
    private array $notPaths = [];

    /**
     * path filters. each filter like: `Closure(SplFileInfo):bool`, return FALSE to exclude.
     *
     * @var array
     */
    private array $filters = [];

    /** @var Iterator[] */
    private array $iterators = [];

    /** @var bool */
    private bool $followLinks = false;

    /**
     * @return FileFinder
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * @param array $config
     *
     * @return FileFinder
     */
    public static function fromArray(array $config): self
    {
        $finder  = new self();
        $allowed = [
            'names'    => 'addNames',
            'notNames' => 'addNotNames',
            'paths'    => 'addNotPaths',
            'notPaths' => 'addNotPaths',
            'exclude'  => 'exclude',
            'excludes' => 'exclude',
        ];

        foreach ($config as $prop => $values) {
            if ($values && isset($allowed[$prop])) {
                $method = $allowed[$prop];
                $finder->$method($values);
            }
        }

        return $finder;
    }

    /**
     * FileFinder constructor.
     */
    public function __construct()
    {
        $this->ignore = self::IGNORE_VCS_FILES | self::IGNORE_DOT_FILES;
    }

    /**
     * @return $this
     */
    public function directories(): self
    {
        $this->mode = self::ONLY_DIR;
        return $this;
    }

    /**
     * @return $this
     */
    public function dirs(): self
    {
        $this->mode = self::ONLY_DIR;
        return $this;
    }

    /**
     * @return $this
     */
    public function onlyDirs(): self
    {
        $this->mode = self::ONLY_DIR;
        return $this;
    }

    /**
     * @return FileFinder
     */
    public function files(): self
    {
        $this->mode = self::ONLY_FILE;
        return $this;
    }

    /**
     * @return FileFinder
     */
    public function onlyFiles(): self
    {
        $this->mode = self::ONLY_FILE;
        return $this;
    }

    /**
     * add include file,dir name match pattern.
     *
     * $finder->name('*.php')
     * $finder->name('test.php')
     *
     * @param string $pattern
     *
     * @return FileFinder
     */
    public function name(string $pattern): self
    {
        return $this->addNames($pattern);
    }

    /**
     * add include file,dir name match pattern.
     *
     * @param array|string $patterns
     *
     * @return FileFinder
     */
    public function addNames(array|string $patterns): self
    {
        if ($patterns) {
            $patterns = is_string($patterns) ? Str::splitTrimmed($patterns) : $patterns;
            // append
            $this->names = array_merge($this->names, $patterns);
        }
        return $this;
    }

    /**
     * add exclude file,dir name patterns
     *
     * @param string $pattern
     *
     * @return FileFinder
     */
    public function notName(string $pattern): self
    {
        $this->notNames[] = $pattern;
        return $this;
    }

    /**
     * add exclude file,dir name patterns
     *
     * @param array|string $patterns
     *
     * @return FileFinder
     */
    public function notNames(array|string $patterns): self
    {
        if ($patterns) {
            $patterns = is_string($patterns) ? Str::splitTrimmed($patterns) : $patterns;
            // append
            $this->notNames = array_merge($this->notNames, $patterns);
        }
        return $this;
    }

    /**
     * add exclude file,dir name patterns
     *
     * @param array|string $patterns
     *
     * @return FileFinder
     */
    public function addNotNames(array|string $patterns): self
    {
        return $this->notNames($patterns);
    }

    /**
     * add include paths pattern.
     * eg:
     * $finder->path('some/special/dir')
     *
     * @param string $pattern
     *
     * @return FileFinder
     */
    public function path(string $pattern): self
    {
        $this->paths[] = $pattern;
        return $this;
    }

    /**
     * add include paths pattern.
     *
     * @param array|string $patterns
     *
     * @return FileFinder
     */
    public function addPaths(array|string $patterns): self
    {
        if ($patterns) {
            $patterns = is_string($patterns) ? Str::splitTrimmed($patterns) : $patterns;
            // append
            $this->paths = array_merge($this->paths, $patterns);
        }
        return $this;
    }

    /**
     * add exclude paths pattern
     *
     * @param string $pattern
     *
     * @return FileFinder
     */
    public function notPath(string $pattern): self
    {
        return $this->addNotPaths($pattern);
    }

    /**
     * add exclude paths pattern. alias of addNotPaths()
     *
     * @param array|string $patterns
     *
     * @return FileFinder
     */
    public function notPaths(array|string $patterns): self
    {
        return $this->addNotPaths($patterns);
    }

    /**
     * add exclude paths pattern.
     * eg: $finder->addNotPaths(['vendor', 'node_modules', 'bin/'])
     *
     * @param array|string $patterns
     *
     * @return FileFinder
     */
    public function addNotPaths(array|string $patterns): self
    {
        if ($patterns) {
            $patterns = is_string($patterns) ? Str::splitTrimmed($patterns) : $patterns;
            // append
            $this->notPaths = array_merge($this->notPaths, $patterns);
        }
        return $this;
    }

    /**
     * exclude pattern for directory names and each sub-dirs
     *
     * @param array|string $dirNames
     *
     * @return FileFinder
     */
    public function exclude(array|string $dirNames): self
    {
        if ($dirNames) {
            $dirNames = is_string($dirNames) ? Str::splitTrimmed($dirNames) : $dirNames;
            // append
            $this->excludes = array_merge($this->excludes, $dirNames);
        }
        return $this;
    }

    /**
     * @param bool $ignoreVCS
     *
     * @return self
     */
    public function ignoreVCS(bool $ignoreVCS): self
    {
        if ($ignoreVCS) {
            $this->ignore |= self::IGNORE_VCS_FILES;
        } else {
            $this->ignore &= ~self::IGNORE_VCS_FILES;
        }
        return $this;
    }

    /**
     * @param bool $ignoreDotFiles
     *
     * @return FileFinder
     */
    public function ignoreDotFiles(bool $ignoreDotFiles = true): self
    {
        if ($ignoreDotFiles) {
            $this->ignore |= self::IGNORE_DOT_FILES;
        } else {
            $this->ignore &= ~self::IGNORE_DOT_FILES;
        }
        return $this;
    }

    /**
     * @param bool $ignoreDotDirs
     *
     * @return FileFinder
     */
    public function ignoreDotDirs(bool $ignoreDotDirs = true): self
    {
        if ($ignoreDotDirs) {
            $this->ignore |= self::IGNORE_DOT_DIRS;
        } else {
            $this->ignore &= ~self::IGNORE_DOT_DIRS;
        }
        return $this;
    }

    /**
     * @param bool $skipUnreadableDirs
     *
     * @return $this
     */
    public function ignoreUnreadableDirs(bool $skipUnreadableDirs = true): self
    {
        return $this->skipUnreadableDirs($skipUnreadableDirs);
    }

    /**
     * @param bool $skipUnreadableDirs
     *
     * @return $this
     */
    public function skipUnreadableDirs(bool $skipUnreadableDirs = true): self
    {
        $this->skipUnreadableDirs = $skipUnreadableDirs;
        return $this;
    }

    /**
     * @return FileFinder
     */
    public function notFollowLinks(): self
    {
        $this->followLinks = false;
        return $this;
    }

    /**
     * @param mixed|bool $followLinks
     *
     * @return FileFinder
     */
    public function followLinks(mixed $followLinks = true): self
    {
        $this->followLinks = (bool)$followLinks;
        return $this;
    }

    /**
     * @return $this
     */
    public function notRecursive(): self
    {
        $this->recursive = false;
        return $this;
    }

    /**
     * @param bool $recursive
     *
     * @return $this
     */
    public function recursiveDir(bool $recursive): self
    {
        $this->recursive = $recursive;
        return $this;
    }

    /**
     * @param Closure(SplFileInfo): bool $closure
     *
     * @return FileFinder
     */
    public function filter(Closure $closure): self
    {
        $this->filters[] = $closure;
        return $this;
    }

    /**
     * @param array|string $dirs
     *
     * @return $this
     */
    public function in(array|string $dirs): self
    {
        return $this->inDir($dirs);
    }

    /**
     * alias of the `in()`
     *
     * @param array|string $dirs
     *
     * @return FileFinder
     */
    public function inDir(array|string $dirs): self
    {
        if ($dirs) {
            $dirs = is_string($dirs) ? Str::splitTrimmed($dirs) : $dirs;
            // append
            $this->dirs = array_merge($this->dirs, $dirs);
        }
        return $this;
    }

    /**
     * @param mixed $iterator
     *
     * @return $this
     * @throws Exception
     */
    public function append(mixed $iterator): self
    {
        if ($iterator instanceof IteratorAggregate) {
            $this->iterators[] = $iterator->getIterator();
        } elseif ($iterator instanceof Iterator) {
            $this->iterators[] = $iterator;
            // } elseif (\is_array($iterator) || $iterator instanceof Traversable) {
        } elseif (is_iterable($iterator)) {
            $it = new ArrayIterator();
            foreach ($iterator as $file) {
                $it->append($file instanceof SplFileInfo ? $file : new SplFileInfo($file));
            }
            $this->iterators[] = $it;
        } else {
            throw new InvalidArgumentException('The argument type is error');
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getInfo(): array
    {
        $this->initialize();
        $info = get_object_vars($this);

        // change mode value
        $info['mode'] = self::MODE2DESC[$this->mode];
        return $info;
    }

    protected function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        if (0 === count($this->dirs) && 0 === count($this->iterators)) {
            throw new LogicException('You must call one of in() or append() methods before iterating over a Finder.');
        }

        if (!$this->ignoreVcsAdded && self::IGNORE_VCS_FILES === (self::IGNORE_VCS_FILES & $this->ignore)) {
            $this->excludes       = array_merge($this->excludes, self::$vcsPatterns);
            $this->ignoreVcsAdded = true;
        }

        if (self::IGNORE_DOT_DIRS === (self::IGNORE_DOT_DIRS & $this->ignore)) {
            $this->excludes[] = '.*';
        }

        if (self::IGNORE_DOT_FILES === (self::IGNORE_DOT_FILES & $this->ignore)) {
            $this->notNames[] = '.*';
        }

        $this->initialized = true;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    /**
     * @return bool
     */
    public function isFollowLinks(): bool
    {
        return $this->followLinks;
    }

    /**
     * @param callable(SplFileInfo): void $fn
     */
    public function each(callable $fn): void
    {
        foreach ($this->getIterator() as $fileInfo) {
            $fn($fileInfo);
        }
    }

    /**
     * Retrieve an external iterator
     *
     * @return Traversable<SplFileInfo> An Traversable
     */
    public function all(): Traversable
    {
        return $this->getIterator();
    }

    /**
     * Retrieve an external iterator
     *
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable<SplFileInfo> An Traversable
     */
    public function getIterator(): Traversable
    {
        $this->initialize();

        if (1 === count($this->dirs) && 0 === count($this->iterators)) {
            return $this->findInDirectory($this->dirs[0]);
        }

        $iterator = new AppendIterator();
        foreach ($this->dirs as $dir) {
            $iterator->append($this->findInDirectory($dir));
        }

        foreach ($this->iterators as $it) {
            $iterator->append($it);
        }

        return $iterator;
    }

    /**
     * @param string $dir
     *
     * @return Iterator
     */
    private function findInDirectory(string $dir): Iterator
    {
        $flags = RecursiveDirectoryIterator::SKIP_DOTS;
        if ($this->followLinks) {
            $flags |= RecursiveDirectoryIterator::FOLLOW_SYMLINKS;
        }

        $iterator = new class($dir, $flags, $this->recursive, $this->skipUnreadableDirs) extends RecursiveDirectoryIterator {
            private string $rootPath;
            private ?string $subPath = null;
            private bool $recursive;
            private bool|null $rewindable = null;

            private string $directorySep;
            private bool $skipUnreadableDirs;

            public function __construct(string $path, int $flags, bool $recursive = true, bool $skipUnreadableDirs = true)
            {
                if ($flags & (self::CURRENT_AS_PATHNAME | self::CURRENT_AS_SELF)) {
                    throw new RuntimeException('This iterator only support returning current as fileInfo.');
                }

                $this->rootPath           = $path;
                $this->recursive          = $recursive;
                $this->skipUnreadableDirs = $skipUnreadableDirs;
                parent::__construct($path, $flags);

                if ('/' !== DIRECTORY_SEPARATOR && !($flags & self::UNIX_PATHS)) {
                    $this->directorySep = DIRECTORY_SEPARATOR;
                }
            }

            public function current(): SplFileInfo
            {
                // vdump($this->getPathname(), $this);
                if (null === $this->subPath) {
                    $this->subPath = $this->getSubPath();
                }

                // if ('' !== $subPathname) {
                //     $subPathname .= $this->directorySep;
                // }

                // $subPathname .= $this->getFilename();

                // $fileInfo = new SplFileInfo($this->getPathname());
                // $fileInfo = new SplFileInfo($this->rootPath . $this->directorySep . $subPathname);
                // vdump($this->rootPath, $subPathname, $fileInfo);
                // add props
                // $fileInfo->relativePath     = $this->subPath;
                // $fileInfo->relativePathname = $subPathname;

                return new SplFileInfo($this->getPathname());
            }

            public function hasChildren(bool $allowLinks = false): bool
            {
                if (!$this->recursive) {
                    return false;
                }

                return parent::hasChildren($allowLinks);
            }

            public function getChildren(): RecursiveDirectoryIterator
            {
                try {
                    $children = parent::getChildren();

                    if ($children instanceof self) {
                        $children->rootPath           = $this->rootPath;
                        $children->rewindable         = &$this->rewindable;
                        $children->skipUnreadableDirs = $this->skipUnreadableDirs;
                    }

                    return $children;
                } catch (UnexpectedValueException $e) {
                    if ($this->skipUnreadableDirs) {
                        return $this;
                        // return null;
                        // return new RecursiveDirectoryIterator([]);
                    }

                    throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
            }

            public function rewind(): void
            {
                if (false === $this->isRewindable()) {
                    return;
                }

                parent::rewind();
            }

            public function isRewindable(): ?bool
            {
                if (null !== $this->rewindable) {
                    return $this->rewindable;
                }

                if (false !== $stream = @opendir($this->getPath())) {
                    $infoS = stream_get_meta_data($stream);
                    closedir($stream);

                    if ($infoS['seekable']) {
                        return $this->rewindable = true;
                    }
                }

                return $this->rewindable = false;
            }
        };

        // exclude directories
        if ($this->excludes) {
            $iterator = new class($iterator, $this->excludes) extends FilterIterator implements RecursiveIterator {
                /** @var array<string> */
                private array $excludes;

                private RecursiveIterator $iterator;

                public function __construct(RecursiveIterator $iterator, array $excludes)
                {
                    $this->excludes = $excludes;
                    $this->iterator = $iterator;

                    parent::__construct($iterator);
                }

                public function accept(): bool
                {
                    if ($this->current()->isDir()) {
                        $name = $this->current()->getFilename();

                        foreach ($this->excludes as $not) {
                            if ($not === $name || fnmatch($not, $name)) {
                                return false;
                            }
                        }
                    }

                    return true;
                }

                public function hasChildren(): bool
                {
                    return $this->iterator->hasChildren();
                }

                public function getChildren(): ?RecursiveIterator
                {
                    if (!$child = $this->iterator->getChildren()) {
                        return null;
                    }

                    $children = new self($child, []);
                    // sync
                    $children->excludes = $this->excludes;
                    return $children;
                }
            };
        }

        // create recursive iterator
        $iterator = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);

        // mode: find files or dirs
        if ($this->mode) {
            $iterator = new class($iterator, $this->mode) extends FilterIterator {
                private int $mode;

                public function __construct(Iterator $iterator, int $mode)
                {
                    $this->mode = $mode;
                    parent::__construct($iterator);
                }

                public function accept(): bool
                {
                    /** @var SplFileInfo $info */
                    $info = $this->current();
                    if (FileFinder::ONLY_DIR === $this->mode && $info->isFile()) {
                        return false;
                    }

                    if (FileFinder::ONLY_FILE === $this->mode && $info->isDir()) {
                        return false;
                    }
                    return true;
                }
            };
        }

        if ($this->names || $this->notNames) {
            $iterator = new class($iterator, $this->names, $this->notNames) extends FilterIterator {
                private array $names;
                private array $notNames;

                public function __construct(Iterator $iterator, array $names, array $notNames)
                {
                    parent::__construct($iterator);
                    $this->names    = $names;
                    $this->notNames = $notNames;
                }

                public function accept(): bool
                {
                    $filename = $this->current()->getFilename();
                    foreach ($this->notNames as $not) {
                        // vdump($not, $this->current()->getPathname(), $filename);
                        if ($not === $filename || fnmatch($not, $filename)) {
                            return false;
                        }
                    }

                    if ($this->names) {
                        foreach ($this->names as $need) {
                            if ($need === $filename || fnmatch($need, $filename)) {
                                return true;
                            }
                        }

                        return false;
                    }

                    return true;
                }
            };
        }

        if ($this->filters) {
            $iterator = new class($iterator, $this->filters) extends FilterIterator {
                private array $filters;

                public function __construct(Iterator $iterator, array $filters)
                {
                    parent::__construct($iterator);
                    $this->filters = $filters;
                }

                public function accept(): bool
                {
                    /** @var SplFileInfo $fileInfo */
                    $fileInfo = $this->current();
                    foreach ($this->filters as $filter) {
                        if (false === $filter($fileInfo)) {
                            return false;
                        }
                    }

                    return true;
                }
            };
        }

        if ($this->paths || $this->notPaths) {
            $iterator = new class($iterator, $this->paths, $this->notPaths) extends FilterIterator {
                /** @var array<string> */
                private array $paths;
                /** @var array<string> */
                private array $notPaths;

                public function __construct(Iterator $iterator, array $paths, array $notPaths)
                {
                    parent::__construct($iterator);
                    $this->paths    = $paths;
                    $this->notPaths = $notPaths;
                }

                public function accept(): bool
                {
                    /** @var string $pathname {@see SplFileInfo::getPathname()} */
                    $pathname = $this->current()->getPathname();
                    if ('\\' === DIRECTORY_SEPARATOR) {
                        $pathname = str_replace('\\', '/', $pathname);
                    }

                    foreach ($this->notPaths as $not) {
                        if (FileFinder::matchPath($pathname, $not)) {
                            return false;
                        }
                    }

                    if ($this->paths) {
                        foreach ($this->paths as $need) {
                            if (FileFinder::matchPath($pathname, $need)) {
                                return true;
                            }
                        }
                        return false;
                    }

                    return true;
                }
            };
        }

        return $iterator;
    }

    /**
     * @param string $path
     * @param string $pattern
     *
     * @return bool
     */
    public static function matchPath(string $path, string $pattern): bool
    {
        if (str_contains($pattern, '*') || str_contains($pattern, '[') || str_contains($pattern, '.')) {
            return fnmatch($pattern, $path);
        }

        return str_contains($path, $pattern);
    }
}
