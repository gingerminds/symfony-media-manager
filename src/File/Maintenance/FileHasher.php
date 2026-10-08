<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File\Maintenance;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\File\FileStorage;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use League\Flysystem\FilesystemException;

/**
 * sha256 of the rows without one (imported databases), read on the disk of each file.
 */
class FileHasher
{
    public const int BATCH_SIZE = 500;

    public function __construct(
        private readonly FileRepository $files,
        private readonly FileStorage $storage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function count(bool $force): int
    {
        return $force ? $this->files->count() : $this->files->count(['hash' => null]);
    }

    /**
     * @param callable(): void|null $progress called after each file
     *
     * @return list<string> "disk:path" of the files missing on their disk
     */
    public function hash(bool $force, ?callable $progress = null): array
    {
        $missing = [];
        $lastId = '';

        do {
            $batch = $this->files->findBatchAfter($lastId, self::BATCH_SIZE, $force ? null : ['hash' => null]);

            foreach ($batch as $file) {
                $hash = $this->compute($file);

                if (null === $hash) {
                    $missing[] = $file->getDisk() . ':' . $file->getPath();
                } else {
                    $file->setHash($hash);
                }

                $lastId = $file->getId();

                if (null !== $progress) {
                    $progress();
                }
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
        } while (self::BATCH_SIZE === \count($batch));

        return $missing;
    }

    private function compute(FileInterface $file): ?string
    {
        try {
            $stream = $this->storage->readStream($file);
        } catch (FilesystemException) {
            return null;
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }
}
