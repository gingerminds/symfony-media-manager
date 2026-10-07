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
        private readonly MimeTypeNormalizer $mimeTypes,
        private readonly FileRepository $files,
        private readonly ImageProcessor $images,
        private readonly string $fileClass,
        private readonly int $maxUploadSize,
        private readonly array $allowedMimes,
    ) {
    }

    /**
     * Stores the file under the library root, renamed "name-1.ext", "name-2.ext"... when the name is taken.
     *
     * @param string $directory relative to the library root
     */
    public function store(\SplFileInfo $file, string $directory = '', ?string $disk = null): FileInterface
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
        $filesystem = $this->disks->get($disk);
        $path = $this->availablePath($filesystem, $disk, $this->paths->inLibrary($directory), $this->paths->fileName($originalName));

        $this->write($filesystem, $path, $realPath, $originalName);

        $entity = new ($this->fileClass)($disk, $path, $mimeType, $originalName, $size, hash_file('sha256', $realPath) ?: null);

        try {
            $this->files->save($entity);
        } catch (\Throwable $exception) {
            $filesystem->delete($path);

            throw $exception;
        }

        return $entity;
    }

    public function delete(FileInterface $file): void
    {
        $this->images->clear($file);
        $this->disks->get($file->getDisk())->delete($file->getPath());
        $this->files->remove($file);
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

    private function availablePath(FilesystemOperator $filesystem, string $disk, string $directory, string $fileName): string
    {
        $name = pathinfo($fileName, \PATHINFO_FILENAME);
        $extension = pathinfo($fileName, \PATHINFO_EXTENSION);
        $suffix = 0;

        do {
            $candidate = $directory . '/' . $name . (0 === $suffix ? '' : '-' . $suffix) . ('' === $extension ? '' : '.' . $extension);
            ++$suffix;
        } while ($filesystem->fileExists($candidate) || $this->files->pathExists($disk, $candidate));

        return $candidate;
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
