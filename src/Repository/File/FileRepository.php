<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Repository\File;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Repository\AbstractRepository;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;

/**
 * @extends AbstractRepository<FileInterface>
 */
class FileRepository extends AbstractRepository
{
    /**
     * @param class-string<FileInterface> $entityClass
     */
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, $entityClass);
    }

    /**
     * The oldest file with this content.
     */
    public function findOneByHash(string $hash): ?FileInterface
    {
        return $this->findOneBy(['hash' => $hash], ['createdAt' => 'ASC']);
    }

    /**
     * Disks holding at least one file.
     *
     * @return list<string>
     */
    public function findDisks(): array
    {
        return array_column($this->createQueryBuilder('f')->select('DISTINCT f.disk')->getQuery()->getScalarResult(), 'disk');
    }

    public function pathExists(string $disk, string $path): bool
    {
        return $this->count(['disk' => $disk, 'path' => $path]) > 0;
    }
}
