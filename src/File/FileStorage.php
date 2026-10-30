<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

use Gingerminds\MediaManagerBundle\Entity\File\BaseFile;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Exception\FileUploadException;
use Gingerminds\MediaManagerBundle\Image\ImageProcessor;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Gingerminds\MediaManagerBundle\Storage\DiskRegistry;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Physical files and their `files` rows.
 */
class FileStorage
{
    /**
     * @param class-string<BaseFile> $fileClass
     * @param list<string>           $allowedMimes
     */
    public function __construct(
        private readonly DiskRegistry $disks,
        private readonly PathGuard $paths,
        private readonly MimeTypeGuesser $mimeTypes,
        private readonly FileRepository $files,
        private readonly ImageProcessor $images,
        private readonly string $fileClass,
        private readonly int $maxUploadSize,
        private readonly array $allowedMimes,
        private readonly PathLocker $locker,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Stores the file under the library root, renamed "name-1.ext", "name-2.ext"... when the name is taken.
     *
     * @param string      $directory relative to the library root
     * @param string|null $hash      sha256 of the content, when already known
     */
    public function store(\SplFileInfo $file, string $directory = '', ?string $disk = null, ?string $hash = null): FileInterface
    {
        $originalName = $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename();
        $realPath = $file->getRealPath();

        if (($file instanceof UploadedFile && !$file->isValid()) || false === $realPath) {
            throw FileUploadException::couldNotStore($originalName);
        }

        $size = (int) $file->getSize();

        if ($size > $this->maxUploadSize * 1024) {
            throw FileUploadException::tooLarge($size, $this->maxUploadSize);
        }

        $mimeType = $this->mimeTypes->guess($realPath);

        if (!\in_array($mimeType, $this->allowedMimes, true)) {
            throw FileUploadException::notAllowed($mimeType);
        }

        $disk ??= $this->disks->defaultDisk();
        $directory = $this->paths->inLibrary($directory);
        $fileName = $this->paths->fileName($originalName);

        return $this->locker->synchronized([[$disk, $directory, $fileName]], fn (): FileInterface => $this->writeRow(
            $disk,
            $this->availablePath($disk, $directory, $fileName),
            $realPath,
            $originalName,
            $mimeType,
            $size,
            $hash ?? (hash_file('sha256', $realPath) ?: null),
        ));
    }

    /**
     * The rows first, in one flush, then the physical files no other row points to (and their
     * presets): a failed flush (a reference added meanwhile) leaves every file in place. A physical
     * file that cannot be deleted is only logged.
     *
     * @param list<FileInterface> $files
     */
    public function delete(array $files): void
    {
        $this->files->removeAll($files);

        foreach ($files as $file) {
            if ($this->files->countByPath($file->getDisk(), $file->getPath()) > 0) {
                continue;
            }

            try {
                $this->images->clear($file);
                $this->disks->get($file->getDisk())->delete($file->getPath());
            } catch (FilesystemException $exception) {
                $this->logger->warning('The file "{path}" of the disk "{disk}" could not be deleted: {message}', [
                    'path' => $file->getPath(),
                    'disk' => $file->getDisk(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function exists(FileInterface $file): bool
    {
        return $this->disks->get($file->getDisk())->fileExists($file->getPath());
    }

    /**
     * @return resource
     *
     * @throws FilesystemException
     */
    public function readStream(FileInterface $file)
    {
        return $this->disks->get($file->getDisk())->readStream($file->getPath());
    }

    /**
     * "name.ext", else "name-1.ext", "name-2.ext"... free on the disk and in `files`. Call it inside
     * PathLocker::synchronized(), up to the write of the path.
     *
     * @param string $directory on the disk
     */
    public function availablePath(string $disk, string $directory, string $fileName): string
    {
        $filesystem = $this->disks->get($disk);
        $name = pathinfo($fileName, \PATHINFO_FILENAME);
        $extension = pathinfo($fileName, \PATHINFO_EXTENSION);
        $suffix = 0;

        do {
            $candidate = $directory . '/' . $name . (0 === $suffix ? '' : '-' . $suffix) . ('' === $extension ? '' : '.' . $extension);
            ++$suffix;
        } while ($filesystem->fileExists($candidate) || $this->files->pathExists($disk, $candidate));

        return $candidate;
    }

    private function writeRow(string $disk, string $path, string $realPath, string $originalName, string $mimeType, int $size, ?string $hash): FileInterface
    {
        $filesystem = $this->disks->get($disk);
        $this->write($filesystem, $path, $realPath, $originalName);
        $entity = new ($this->fileClass)($disk, $path, $mimeType, $originalName, $size, $hash);

        try {
            $this->files->save($entity);
        } catch (\Throwable $exception) {
            $filesystem->delete($path);

            throw $exception;
        }

        return $entity;
    }

    private function write(FilesystemOperator $filesystem, string $path, string $realPath, string $originalName): void
    {
        $stream = fopen($realPath, 'r');

        if (false === $stream) {
            throw FileUploadException::couldNotStore($originalName);
        }

        try {
            $filesystem->writeStream($path, $stream);
        } catch (FilesystemException $exception) {
            throw FileUploadException::couldNotStore($originalName, $exception);
        } finally {
            if (\is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
