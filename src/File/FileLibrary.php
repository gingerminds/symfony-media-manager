<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Pagination\Paginator;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Event\FileUploadingEvent;
use Gingerminds\MediaManagerBundle\Exception\FileInUseException;
use Gingerminds\MediaManagerBundle\Exception\FileUploadException;
use Gingerminds\MediaManagerBundle\Exception\LibraryException;
use Gingerminds\MediaManagerBundle\File\Reference\FileReferenceRegistry;
use Gingerminds\MediaManagerBundle\Image\ImageProcessor;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Gingerminds\MediaManagerBundle\Storage\DiskRegistry;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The files of the library (the `files` table): listing, upload, rename, move, delete, merge. Paths
 * are relative to the library root; the folders are LibraryDirectories'.
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
        private readonly LibraryDirectories $directories,
        private readonly int $itemsPerPage,
        private readonly PathLocker $locker,
        private readonly ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * @return Paginator<FileInterface>
     */
    public function files(LibraryQuery $query): Paginator
    {
        return $this->files->paginateLibrary(
            $this->disks->defaultDisk(),
            $this->directories->existing($query->directory),
            $query,
            $query->orphans ? $this->references->excludeReferenced(...) : null,
            min(max(1, $query->itemsPerPage ?? $this->itemsPerPage), 500),
        );
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

        // Two uploads of one content: the second one finds the row of the first one.
        return $this->locker->content($hash, function () use ($file, $directory, $hash): UploadResult {
            $existing = $this->files->findOneByHash($hash);

            if ($existing instanceof FileInterface) {
                return new UploadResult($existing, true);
            }

            $directory = $this->paths->relative($this->directories->existing($directory));
            $this->events?->dispatch(new FileUploadingEvent($file, $directory));

            return new UploadResult($this->storage->store($file, $directory, hash: $hash));
        });
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

    public function setAlt(FileInterface $file, ?string $alt): void
    {
        if (mb_strlen(trim($alt ?? '')) > FileInterface::ALT_MAX_LENGTH) {
            throw LibraryException::altTooLong(FileInterface::ALT_MAX_LENGTH);
        }

        $file->setAlt($alt);
        $this->entityManager->flush();
    }

    /**
     * @param list<FileInterface> $files
     */
    public function move(array $files, string $directory): void
    {
        $this->relocate($files, $this->directories->existing($directory));
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
            } else {
                $deleted[] = $file;
            }
        }

        $this->storage->delete($deleted);

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
     * Every reference to the duplicates now points to $keep, then the duplicates are deleted. $keep
     * takes the alt of the first duplicate having one when it has none.
     *
     * @param list<FileInterface> $duplicates same content (hash) as $keep
     *
     * @return int number of updated entities
     */
    public function mergeDuplicates(FileInterface $keep, array $duplicates): int
    {
        $updated = 0;
        $merged = [];

        foreach ($duplicates as $duplicate) {
            if ($duplicate->getId() === $keep->getId()) {
                continue;
            }

            if (null === $keep->getHash() || $duplicate->getHash() !== $keep->getHash()) {
                throw new \InvalidArgumentException(\sprintf('The file "%s" is not a duplicate of "%s".', $duplicate->getId(), $keep->getId()));
            }

            if (null === $keep->getAlt()) {
                $keep->setAlt($duplicate->getAlt());
            }

            $updated += $this->references->replace($duplicate, $keep);
            $merged[] = $duplicate;
        }

        // Flushes the alt of $keep too.
        $this->storage->delete($merged);

        return $updated;
    }

    /**
     * Moves the files on their disk, then saves their paths; the files are moved back if the save fails.
     * The target names stay locked until the save.
     *
     * @param list<FileInterface> $files
     * @param string              $directory on the disk
     */
    private function relocate(array $files, string $directory, ?string $fileName = null): void
    {
        $moves = [];

        foreach ($files as $file) {
            $name = $fileName ?? basename($file->getPath());

            if ($directory . '/' . $name !== $file->getPath()) {
                $moves[] = [$file, $name];
            }
        }

        if ([] === $moves) {
            return;
        }

        $this->locker->synchronized(
            array_map(static fn (array $move): array => [$move[0]->getDisk(), $directory, $move[1]], $moves),
            function () use ($moves, $directory): void {
                $this->moveFiles($moves, $directory);
            },
        );
    }

    /**
     * @param list<array{FileInterface, string}> $moves file, wanted name
     */
    private function moveFiles(array $moves, string $directory): void
    {
        $moved = [];

        try {
            foreach ($moves as [$file, $name]) {
                $from = $file->getPath();
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
}
