<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File\Maintenance;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\File\DeleteResult;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\File\Reference\FileReferenceRegistry;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;

/**
 * Files used nowhere (file reference registry).
 */
class OrphanFinder
{
    public function __construct(
        private readonly FileRepository $files,
        private readonly FileReferenceRegistry $references,
        private readonly FileLibrary $library,
    ) {
    }

    /**
     * @param int|null $olderThan days: only the files created before (a file without date counts as old)
     *
     * @return list<FileInterface>
     */
    public function find(?int $olderThan = null): array
    {
        $qb = $this->files->createQueryBuilder('f')->orderBy('f.path');
        $this->references->excludeReferenced($qb, 'f');

        if (null !== $olderThan) {
            $qb->andWhere('f.createdAt IS NULL OR f.createdAt < :limit')->setParameter('limit', new \DateTimeImmutable('-' . $olderThan . ' days'));
        }

        /** @var list<FileInterface> */
        return $qb->getQuery()->getResult();
    }

    /**
     * The library checks the usages again: a file used in between is kept.
     *
     * @param list<FileInterface> $files
     */
    public function delete(array $files): DeleteResult
    {
        return $this->library->delete($files);
    }
}
