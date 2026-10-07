<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Pagination\Paginator;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Exception\FileInUseException;
use Gingerminds\MediaManagerBundle\Exception\FileUploadException;
use Gingerminds\MediaManagerBundle\Exception\LibraryException;
use Gingerminds\MediaManagerBundle\File\Reference\FileReferenceRegistry;
use Gingerminds\MediaManagerBundle\Image\ImageProcessor;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Gingerminds\MediaManagerBundle\Storage\DiskRegistry;
use League\Flysystem\FilesystemOperator;

/**
 * The file library: directories on the library disk, files from the `files` table. Paths are
 * relative to the library root.
 */
class FileLibrary
{
    public function __construct(
        private readonly FileStorage $storage,
        private readonly FileRepository $files,
        private readonly FileReferenceRegistry $references,
        private readonly DiskRegistry $disks,
        private readonly PathGuard $paths,
        private readonly ImageProcessor $images,
        private readonly EntityManagerInterface $entityManager,
        private readonly int $itemsPerPage,
    ) {
    }

    /**
     * Subdirectories, hidden ones (".cache"...) excluded.
     *
     * @return list<string>
     */
    public function directories(string $path = ''): array
    {
        $directory = $this->existingDirectory($path);
        $directories = [];

        foreach ($this->filesystem()->listContents($directory, false) as $item) {
            if ($item->isDir() && !str_starts_with(basename($item->path()), '.')) {
                $directories[] = $this->paths->relative($item->path());
            }
        }

        natcasesort($directories);

        return array_values($directories);
    }

    public function hasDirectories(string $path): bool
    {
        foreach ($this->filesystem()->listContents($this->existingDirectory($path), false) as $item) {
            if ($item->isDir() && !str_starts_with(basename($item->path()), '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Paginator<FileInterface>
     */
    public function files(LibraryQuery $query): Paginator
    {
        return $this->files->paginateLibrary(
            $this->disks->defaultDisk(),
            $this->existingDirectory($query->directory),
            $query,
            $query->orphans ? $this->references->usedIds() : [],
            min(max(1, $query->itemsPerPage ?? $this->itemsPerPage), 500),
        );
    }

    /**
     * @return string the new directory, relative to the library root
     */
    public function mkdir(string $parent, string $name): string
    {
        $directory = $this->existingDirectory($parent) . '/' . $this->paths->directoryName($name);

        if ($this->filesystem()->directoryExists($directory)) {
            throw LibraryException::directoryExists($this->paths->relative($directory));
        }

        $this->filesystem()->createDirectory($directory);

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

        $directory = $this->existingDirectory($path);

        if (
            [] !== $this->filesystem()->listContents($directory, false)->toArray()
            || $this->files->hasFilesUnder($this->disks->defaultDisk(), $directory)
        ) {
            throw LibraryException::directoryNotEmpty($path);
        }

        $this->filesystem()->deleteDirectory($directory);
    }

    /**
     * Content already in the library: nothing is stored, the existing file is returned.
     */
    public function upload(\SplFileInfo $file, string $directory = ''): UploadResult
    {
        $realPath = $file->getRealPath();
        $hash = false !== $realPath ? hash_file('sha256', $realPath) : false;

        if (false === $hash) {
            throw FileUploadException::couldNotStore($file->getFilename());
        }

        $existing = $this->files->findOneByHash($hash);

        if ($existing instanceof FileInterface) {
            return new UploadResult($existing, true);
        }

        return new UploadResult($this->storage->store($file, $this->paths->relative($this->existingDirectory($directory))));
    }

    /**
     * New name, same directory and extension: "Rapport final" for "rapport.pdf" gives "rapport-final.pdf".
     */
    public function rename(FileInterface $file, string $name): void
    {
        $extension = pathinfo($file->getPath(), \PATHINFO_EXTENSION);
        $name = trim('' === $extension ? $name : (string) preg_replace('/\.' . preg_quote($extension, '/') . '$/i', '', trim($name)));

        if ('' === $name) {
            return;
        }

        $previousName = $file->getOriginalName();
        $originalName = $name . ('' === $extension ? '' : '.' . $extension);
        $file->setOriginalName($originalName);

        try {
            $this->relocate([$file], \dirname($file->getPath()), $this->paths->fileName($originalName));
        } catch (\Throwable $exception) {
            $file->setOriginalName($previousName);

            throw $exception;
        }
    }

    /**
     * @param list<FileInterface> $files
     */
    public function move(array $files, string $directory): void
    {
        $this->relocate($files, $this->existingDirectory($directory));
    }

    /**
     * Unused files are deleted, used ones are kept and returned with their usages.
     *
     * @param list<FileInterface> $files
     */
    public function delete(array $files): DeleteResult
    {
        $usages = $this->references->usages($files);
        $deleted = [];
        $blocked = [];

        foreach ($files as $file) {
            if ([] !== ($usages[$file->getId()] ?? [])) {
                $blocked[] = ['file' => $file, 'usages' => $usages[$file->getId()]];

                continue;
            }

            $this->storage->delete($file);
            $deleted[] = $file;
        }

        return new DeleteResult($deleted, $blocked);
    }

    /**
     * @throws FileInUseException
     */
    public function deleteFile(FileInterface $file): void
    {
        $result = $this->delete([$file]);

        if ([] !== $result->blocked) {
            throw new FileInUseException($file, $result->blocked[0]['usages']);
        }
    }

    /**
     * Every reference to the duplicates now points to $keep, then the duplicates are deleted.
     *
     * @param list<FileInterface> $duplicates same content (hash) as $keep
     *
     * @return int number of updated entities
     */
    public function mergeDuplicates(FileInterface $keep, array $duplicates): int
    {
        $updated = 0;

        foreach ($duplicates as $duplicate) {
            if ($duplicate->getId() === $keep->getId()) {
                continue;
            }

            if (null === $keep->getHash() || $duplicate->getHash() !== $keep->getHash()) {
                throw new \InvalidArgumentException(\sprintf('The file "%s" is not a duplicate of "%s".', $duplicate->getId(), $keep->getId()));
            }

            $updated += $this->references->replace($duplicate, $keep);
            $this->storage->delete($duplicate);
        }

        return $updated;
    }

    /**
     * Moves the files on their disk, then saves their paths; the files are moved back if the save fails.
     *
     * @param list<FileInterface> $files
     * @param string              $directory on the disk
     */
    private function relocate(array $files, string $directory, ?string $fileName = null): void
    {
        $moved = [];

        try {
            foreach ($files as $file) {
                $from = $file->getPath();
                $name = $fileName ?? basename($from);

                if ($directory . '/' . $name === $from) {
                    continue;
                }

                $to = $this->storage->availablePath($file->getDisk(), $directory, $name);
                $this->images->clear($file);
                $this->disks->get($file->getDisk())->move($from, $to);
                $file->setPath($to);
                $moved[] = [$file, $from, $to];
            }

            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            foreach (array_reverse($moved) as [$file, $from, $to]) {
                $this->disks->get($file->getDisk())->move($to, $from);
                $file->setPath($from);
            }

            throw $exception;
        }
    }

    /**
     * @return string the directory on the disk
     */
    private function existingDirectory(string $path): string
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
