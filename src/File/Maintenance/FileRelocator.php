<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File\Maintenance;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\File\FileStorage;
use Gingerminds\MediaManagerBundle\File\PathGuard;
use Gingerminds\MediaManagerBundle\Image\ImageProcessor;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Gingerminds\MediaManagerBundle\Storage\DiskRegistry;

/**
 * Files outside `library.root` (e.g. "uploads/..." of a Laravel database) moved to the first level of
 * the root, on their own disk; "name-1.ext" when the name is taken. The rows sharing a physical file
 * move together.
 */
class FileRelocator
{
    public function __construct(
        private readonly FileRepository $files,
        private readonly FileStorage $storage,
        private readonly DiskRegistry $disks,
        private readonly PathGuard $paths,
        private readonly ImageProcessor $images,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, non-empty-list<FileInterface>> rows by "disk:path"
     */
    public function outside(): array
    {
        $groups = [];

        foreach ($this->files->findDisks() as $disk) {
            foreach ($this->files->findOutside($disk, $this->paths->root()) as $file) {
                $groups[$disk . ':' . $file->getPath()][] = $file;
            }
        }

        return $groups;
    }

    /**
     * @param array<string, non-empty-list<FileInterface>> $groups   from outside()
     * @param callable(): void|null                        $progress called after each physical file
     *
     * @return array{moved: array<string, string>, missing: list<string>} old => new "disk:path", and the missing files
     */
    public function relocate(array $groups, ?callable $progress = null): array
    {
        $result = ['moved' => [], 'missing' => []];

        foreach ($groups as $key => $rows) {
            $target = $this->move($rows);

            if (null === $target) {
                $result['missing'][] = $key;
            } else {
                $result['moved'][$key] = $rows[0]->getDisk() . ':' . $target;
            }

            if (null !== $progress) {
                $progress();
            }
        }

        return $result;
    }

    /**
     * @param non-empty-list<FileInterface> $rows the rows of one physical file
     *
     * @return string|null the new path, null when the file is missing on its disk
     */
    private function move(array $rows): ?string
    {
        $disk = $rows[0]->getDisk();
        $from = $rows[0]->getPath();
        $filesystem = $this->disks->get($disk);

        if (!$filesystem->fileExists($from)) {
            return null;
        }

        $to = $this->storage->availablePath($disk, $this->paths->root(), basename($from));
        array_map($this->images->clear(...), $rows);
        $filesystem->move($from, $to);

        try {
            array_map(static fn (FileInterface $file) => $file->setPath($to), $rows);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $filesystem->move($to, $from);
            array_map(static fn (FileInterface $file) => $file->setPath($from), $rows);

            throw $exception;
        }

        return $to;
    }
}
