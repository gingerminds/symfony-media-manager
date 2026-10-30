<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

use Gingerminds\MediaManagerBundle\Exception\LibraryException;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Gingerminds\MediaManagerBundle\Storage\DiskRegistry;
use League\Flysystem\FilesystemOperator;

/**
 * The folders of the library, on its disk: list, create, delete, move and rename. Paths are
 * relative to the library root.
 */
class LibraryDirectories
{
    public function __construct(
        private readonly DiskRegistry $disks,
        private readonly PathGuard $paths,
        private readonly FileRepository $files,
        private readonly DirectoryMover $directoryMover,
        private readonly PathLocker $locker,
    ) {
    }

    /**
     * Subdirectories, hidden ones (".cache"...) excluded.
     *
     * @return list<string>
     */
    public function directories(string $path = ''): array
    {
        $directory = $this->existing($path);
        $directories = [];

        foreach ($this->filesystem()->listContents($directory, false) as $item) {
            if ($item->isDir() && !str_starts_with(basename($item->path()), '.')) {
                $directories[] = $this->paths->relative($item->path());
            }
        }

        natcasesort($directories);

        return array_values($directories);
    }

    /**
     * Whether the directory has subdirectories (hidden ones excluded), without listing them all.
     */
    public function hasDirectories(string $path): bool
    {
        foreach ($this->filesystem()->listContents($this->existing($path), false) as $item) {
            if ($item->isDir() && !str_starts_with(basename($item->path()), '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string the new directory, relative to the library root
     */
    public function mkdir(string $parent, string $name): string
    {
        $parent = $this->existing($parent);
        $name = $this->paths->directoryName($name);
        $directory = $parent . '/' . $name;

        // Locked as a target name: not created in a directory being moved.
        $this->locker->synchronized([[$this->disks->defaultDisk(), $parent, $name]], function () use ($directory): void {
            if ($this->filesystem()->directoryExists($directory)) {
                throw LibraryException::directoryExists($this->paths->relative($directory));
            }

            $this->filesystem()->createDirectory($directory);
        });

        return $this->paths->relative($directory);
    }

    /**
     * Empty directories only.
     */
    public function rmdir(string $path): void
    {
        if ('' === $this->paths->normalize(ltrim($path, '/'))) {
            throw LibraryException::rootDirectory();
        }

        $directory = $this->existing($path);

        if (
            [] !== $this->filesystem()->listContents($directory, false)->toArray()
            || $this->files->hasFilesUnder($this->disks->defaultDisk(), $directory)
        ) {
            throw LibraryException::directoryNotEmpty($path);
        }

        $this->filesystem()->deleteDirectory($directory);
    }

    /**
     * Empty directories are deleted, the others are kept and returned.
     *
     * @param list<string> $paths
     *
     * @return array{deleted: list<string>, kept: list<string>}
     */
    public function rmdirs(array $paths): array
    {
        $result = ['deleted' => [], 'kept' => []];

        foreach ($paths as $path) {
            try {
                $this->rmdir($path);
                $result['deleted'][] = $path;
            } catch (LibraryException $exception) {
                if (!$exception->isDirectoryNotEmpty()) {
                    throw $exception;
                }

                $result['kept'][] = $path;
            }
        }

        return $result;
    }

    /**
     * Moves a directory under $parent, renamed when $name is given; returns its new path.
     *
     * @param int $maxFiles 0: no limit
     */
    public function moveDirectory(string $path, string $parent, ?string $name = null, int $maxFiles = 0): string
    {
        return $this->moveDirectories([$path], $parent, $name, $maxFiles)[0];
    }

    /**
     * Moves directories under $parent; returns their new paths. Everything on the disk follows (files
     * missing from `files` too), the rows get their new paths and the presets are purged. Every move
     * is checked first and $maxFiles counts the files of all the directories; a directory that fails
     * is moved back, the previous ones stay moved.
     *
     * @param list<string> $paths    a subdirectory of another one follows it
     * @param string|null  $name     new name, one directory only
     * @param int          $maxFiles 0: no limit
     *
     * @return list<string>
     */
    public function moveDirectories(array $paths, string $parent, ?string $name = null, int $maxFiles = 0): array
    {
        if (null !== $name && 1 !== \count($paths)) {
            throw new \InvalidArgumentException('Only one directory is renamed at a time.');
        }

        $sources = $this->directorySources($paths);
        $targets = $this->directoryMover->move($sources, $this->existing($parent), $name, $maxFiles);

        return array_map($this->paths->relative(...), $targets);
    }

    /**
     * The directories on the disk, without the subdirectories of another one (they follow it).
     *
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function directorySources(array $paths): array
    {
        $sources = [];

        foreach ($paths as $path) {
            if ('' === $this->paths->normalize(ltrim($path, '/'))) {
                throw LibraryException::rootDirectoryMoved();
            }

            $sources[$this->existing($path)] = true;
        }

        $sources = array_keys($sources);

        return array_values(array_filter($sources, static fn (string $source): bool => !array_any(
            $sources,
            static fn (string $other): bool => str_starts_with($source, $other . '/'),
        )));
    }

    /**
     * An existing directory relative to the library root, as a path on the disk.
     *
     * @return string the directory on the disk
     */
    public function existing(string $path): string
    {
        $directory = $this->paths->inLibrary($path);

        if ($directory !== $this->paths->root() && !$this->filesystem()->directoryExists($directory)) {
            throw LibraryException::directoryNotFound($this->paths->relative($directory));
        }

        return $directory;
    }

    private function filesystem(): FilesystemOperator
    {
        return $this->disks->get($this->disks->defaultDisk());
    }
}
