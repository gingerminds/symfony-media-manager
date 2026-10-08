<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File\Maintenance;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;

/**
 * Rows with the same hash merged into the oldest one: references updated, duplicates deleted
 * (a physical file shared by another row is kept).
 */
class FileDeduplicator
{
    public function __construct(
        private readonly FileRepository $files,
        private readonly FileLibrary $library,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<array{keep: string, duplicates: list<string>, updated: int|null}> "disk:path" of the
     *                                                                                files; updated: null on a dry run
     */
    public function deduplicate(bool $dryRun): array
    {
        $report = [];

        foreach ($this->files->findDuplicatedHashes() as $hash) {
            [$keep, $duplicates] = $this->group($hash);

            $report[] = [
                'keep' => $this->label($keep),
                'duplicates' => array_map($this->label(...), $duplicates),
                'updated' => $dryRun ? null : $this->library->mergeDuplicates($keep, $duplicates),
            ];

            $this->entityManager->clear();
        }

        return $report;
    }

    /**
     * @return array{FileInterface, list<FileInterface>}
     */
    private function group(string $hash): array
    {
        $files = $this->files->findByHashOldestFirst($hash);
        $keep = array_shift($files);

        if (!$keep instanceof FileInterface) {
            throw new \LogicException(\sprintf('No file has the hash "%s".', $hash));
        }

        return [$keep, $files];
    }

    private function label(FileInterface $file): string
    {
        return $file->getDisk() . ':' . $file->getPath();
    }
}
