<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File\Maintenance;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Exception\LibraryException;
use Gingerminds\MediaManagerBundle\File\MimeTypeNormalizer;
use Gingerminds\MediaManagerBundle\File\PathGuard;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Gingerminds\MediaManagerBundle\Storage\DiskRegistry;
use League\Flysystem\FilesystemOperator;

/**
 * Rows for the files of the library disk missing from `files` (copied by hand, imported...), whatever their type.
 */
class FileIndexer
{
    /**
     * @param class-string<FileInterface> $fileClass
     */
    public function __construct(
        private readonly FileRepository $files,
        private readonly DiskRegistry $disks,
        private readonly PathGuard $paths,
        private readonly MimeTypeNormalizer $mimeTypes,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $fileClass,
    ) {
    }

    /**
     * Files of the directory and its subdirectories without a row; hidden directories (".cache"...) excluded.
     *
     * @param string $directory relative to the library root
     *
     * @return list<string> paths on the disk
     */
    public function missing(string $directory = ''): array
    {
        $root = $this->paths->inLibrary($directory);
        $filesystem = $this->filesystem();

        if ($root !== $this->paths->root() && !$filesystem->directoryExists($root)) {
            throw LibraryException::directoryNotFound($directory);
        }

        $known = $this->files->findPathsUnder($this->disks->defaultDisk(), $root);
        $missing = [];

        foreach ($filesystem->listContents($root, true) as $item) {
            $path = $item->path();

            if ($item->isFile() && !isset($known[$path]) && !$this->isHidden(substr($path, \strlen($root)))) {
                $missing[] = $path;
            }
        }

        sort($missing);

        return $missing;
    }

    /**
     * @param list<string>          $paths    from missing()
     * @param callable(): void|null $progress called after each file
     */
    public function index(array $paths, ?callable $progress = null): void
    {
        $disk = $this->disks->defaultDisk();

        foreach ($paths as $index => $path) {
            $this->entityManager->persist($this->createFile($disk, $path));

            if (0 === ($index + 1) % FileHasher::BATCH_SIZE) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }

            if (null !== $progress) {
                $progress();
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * Type, size and hash from a local copy, as for an upload.
     */
    private function createFile(string $disk, string $path): FileInterface
    {
        $copy = (string) tempnam(sys_get_temp_dir(), 'gm-index');

        try {
            $stream = $this->filesystem()->readStream($path);
            file_put_contents($copy, $stream);
            fclose($stream);

            return new ($this->fileClass)($disk, $path, $this->mimeTypes->guess($copy), basename($path), (int) filesize($copy), hash_file('sha256', $copy) ?: null);
        } finally {
            @unlink($copy);
        }
    }

    private function isHidden(string $relativePath): bool
    {
        return array_any(explode('/', $relativePath), static fn (string $segment): bool => str_starts_with($segment, '.'));
    }

    private function filesystem(): FilesystemOperator
    {
        return $this->disks->get($this->disks->defaultDisk());
    }
}
