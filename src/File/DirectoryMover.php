<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\MediaManagerBundle\Exception\LibraryException;
use Gingerminds\MediaManagerBundle\Image\ImageProcessor;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Gingerminds\MediaManagerBundle\Storage\DiskRegistry;
use League\Flysystem\FilesystemOperator;

/**
 * Moves directories of the library disk (FileLibrary::moveDirectories()). Everything on the disk
 * follows, files missing from `files` too; the rows get their new paths and the presets are purged.
 */
class DirectoryMover
{
    public function __construct(
        private readonly FileRepository $files,
        private readonly DiskRegistry $disks,
        private readonly PathGuard $paths,
        private readonly ImageProcessor $images,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Every move is checked first and $maxFiles counts the files of all the directories; a directory
     * that fails is moved back, the previous ones stay moved.
     *
     * @param list<string> $sources  existing directories on the disk, none inside another one
     * @param string       $parent   existing directory on the disk
     * @param string|null  $name     new name, one directory only
     * @param int          $maxFiles 0: no limit
     *
     * @return list<string> the new directories on the disk
     */
    public function move(array $sources, string $parent, ?string $name, int $maxFiles): array
    {
        $moves = [];
        $targets = [];
        $count = 0;

        foreach ($sources as $source) {
            $target = $this->directoryTarget($source, $parent, $name, $targets);
            $targets[$target] = true;
            $contents = $target === $source ? [[], []] : $this->directoryContents($source);
            $count += \count($contents[1]);
            $moves[] = [$source, $target, ...$contents];
        }

        if ($maxFiles > 0 && $count > $maxFiles) {
            throw LibraryException::directoryTooLarge(implode(', ', array_map($this->paths->relative(...), $sources)), $count, $maxFiles);
        }

        foreach ($moves as [$source, $target, $directories, $files]) {
            if ($source !== $target) {
                $this->relocateDirectory($source, $target, $directories, $files);
            }
        }

        return array_column($moves, 1);
    }

    /**
     * Where $source goes under $parent; not into itself nor onto an existing directory.
     *
     * @param array<string, true> $targets targets of the other moved directories
     */
    private function directoryTarget(string $source, string $parent, ?string $name, array $targets): string
    {
        $target = $parent . '/' . (null === $name ? basename($source) : $this->paths->directoryName($name));

        if ($target === $source) {
            return $target;
        }

        if (str_starts_with($target . '/', $source . '/')) {
            throw LibraryException::directoryIntoItself($this->paths->relative($source));
        }

        if (isset($targets[$target]) || $this->filesystem()->directoryExists($target)) {
            throw LibraryException::directoryExists($this->paths->relative($target));
        }

        return $target;
    }

    /**
     * @return array{list<string>, list<string>} the subdirectories and the files, recursively
     */
    private function directoryContents(string $directory): array
    {
        $directories = [];
        $files = [];

        foreach ($this->filesystem()->listContents($directory, true) as $item) {
            if ($item->isDir()) {
                $directories[] = $item->path();
            } else {
                $files[] = $item->path();
            }
        }

        return [$directories, $files];
    }

    /**
     * Moves everything on the disk, then saves the paths of the rows; moved back if anything fails.
     *
     * @param list<string> $directories
     * @param list<string> $files
     */
    private function relocateDirectory(string $source, string $target, array $directories, array $files): void
    {
        $filesystem = $this->filesystem();
        $targetOf = static fn (string $from): string => $target . substr($from, \strlen($source));
        $rows = $this->files->findUnder($this->disks->defaultDisk(), $source);
        $previousPaths = [];
        $moved = [];

        try {
            $filesystem->createDirectory($target);

            foreach ($directories as $directory) {
                $filesystem->createDirectory($targetOf($directory));
            }

            foreach ($files as $from) {
                $filesystem->move($from, $targetOf($from));
                $moved[] = $from;
            }

            foreach ($rows as $file) {
                $this->images->clear($file);
                $previousPaths[] = [$file, $file->getPath()];
                $file->setPath($targetOf($file->getPath()));
            }

            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            foreach (array_reverse($moved) as $from) {
                $filesystem->move($targetOf($from), $from);
            }

            foreach ($previousPaths as [$file, $previousPath]) {
                $file->setPath($previousPath);
            }

            $filesystem->deleteDirectory($target);

            throw $exception;
        }

        $filesystem->deleteDirectory($source);
    }

    private function filesystem(): FilesystemOperator
    {
        return $this->disks->get($this->disks->defaultDisk());
    }
}
