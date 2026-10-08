<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Repository\File;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Pagination\Paginator;
use Gingerminds\CoreBundle\Repository\AbstractRepository;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\File\LibraryQuery;
use Gingerminds\MediaManagerBundle\File\MimeTypePatterns;

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
     * The next rows by id, to walk the table in batches (the entity manager can be cleared between them).
     *
     * @param array<string, mixed>|null $criteria
     *
     * @return list<FileInterface>
     */
    public function findBatchAfter(string $lastId, int $size, ?array $criteria = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.id > :last')
            ->setParameter('last', $lastId)
            ->orderBy('f.id')
            ->setMaxResults($size);

        foreach ($criteria ?? [] as $field => $value) {
            null === $value
                ? $qb->andWhere(\sprintf('f.%s IS NULL', $field))
                : $qb->andWhere(\sprintf('f.%1$s = :%1$s', $field))->setParameter($field, $value);
        }

        /** @var list<FileInterface> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Paths of the rows under a directory of a disk, as keys.
     *
     * @param string $directory on the disk
     *
     * @return array<string, true>
     */
    public function findPathsUnder(string $disk, string $directory): array
    {
        $paths = $this->createQueryBuilder('f')
            ->select('f.path')
            ->where('f.disk = :disk')
            ->andWhere("f.path LIKE :prefix ESCAPE '!'")
            ->setParameter('disk', $disk)
            ->setParameter('prefix', $this->likePrefix($directory))
            ->getQuery()
            ->getSingleColumnResult();

        return array_fill_keys(array_map(strval(...), $paths), true);
    }

    /**
     * Rows of a disk outside a directory (e.g. "uploads/..." of a Laravel database), by path.
     *
     * @param string $directory on the disk
     *
     * @return list<FileInterface>
     */
    public function findOutside(string $disk, string $directory): array
    {
        /** @var list<FileInterface> */
        return $this->createQueryBuilder('f')
            ->where('f.disk = :disk')
            ->andWhere("f.path NOT LIKE :prefix ESCAPE '!'")
            ->setParameter('disk', $disk)
            ->setParameter('prefix', $this->likePrefix($directory))
            ->orderBy('f.path')
            ->getQuery()
            ->getResult();
    }

    /**
     * Hashes shared by several rows.
     *
     * @return list<string>
     */
    public function findDuplicatedHashes(): array
    {
        return array_values(array_map(strval(...), $this->createQueryBuilder('f')
            ->select('f.hash')
            ->where('f.hash IS NOT NULL')
            ->groupBy('f.hash')
            ->having('COUNT(f.id) > 1')
            ->orderBy('f.hash')
            ->getQuery()
            ->getSingleColumnResult()));
    }

    /**
     * Oldest first: the one kept by a deduplication.
     *
     * @return list<FileInterface>
     */
    public function findByHashOldestFirst(string $hash): array
    {
        /** @var list<FileInterface> */
        return $this->createQueryBuilder('f')
            ->where('f.hash = :hash')
            ->setParameter('hash', $hash)
            ->orderBy('f.createdAt')
            ->addOrderBy('f.id')
            ->getQuery()
            ->getResult();
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
        return $this->countByPath($disk, $path) > 0;
    }

    /**
     * Rows sharing a physical file (collisions of the Laravel uploads, without unique names).
     */
    public function countByPath(string $disk, string $path): int
    {
        return $this->count(['disk' => $disk, 'path' => $path]);
    }

    public function hasFilesUnder(string $disk, string $directory): bool
    {
        return null !== $this->createQueryBuilder('f')
            ->select('f.id')
            ->where('f.disk = :disk')
            ->andWhere("f.path LIKE :prefix ESCAPE '!'")
            ->setParameter('disk', $disk)
            ->setParameter('prefix', $this->likePrefix($directory))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Files of a directory and of its subdirectories.
     *
     * @param string $directory on the disk
     *
     * @return list<FileInterface>
     */
    public function findUnder(string $disk, string $directory): array
    {
        /** @var list<FileInterface> */
        return $this->createQueryBuilder('f')
            ->where('f.disk = :disk')
            ->andWhere("f.path LIKE :prefix ESCAPE '!'")
            ->setParameter('disk', $disk)
            ->setParameter('prefix', $this->likePrefix($directory))
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string       $directory on the disk
     * @param list<string> $usedIds   excluded by the orphans filter
     *
     * @return Paginator<FileInterface>
     */
    public function paginateLibrary(string $disk, string $directory, LibraryQuery $query, array $usedIds, int $itemsPerPage): Paginator
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.disk = :disk')
            ->andWhere("f.path LIKE :prefix ESCAPE '!'")
            ->setParameter('disk', $disk)
            ->setParameter('prefix', $this->likePrefix($directory));

        if (!$query->recursive) {
            $qb->andWhere("f.path NOT LIKE :nested ESCAPE '!'")->setParameter('nested', $this->likePrefix($directory) . '/%');
        }

        if (null !== $query->search && '' !== trim($query->search)) {
            // Not the path: its directories would match every file they hold.
            $qb->andWhere("LOWER(f.originalName) LIKE :search ESCAPE '!'")
                ->setParameter('search', '%' . $this->escapeLike(mb_strtolower(trim($query->search))) . '%');
        }

        if (null !== $query->type && isset(LibraryQuery::TYPES[$query->type])) {
            $this->andWhereMimeTypeLike($qb, 'type', LibraryQuery::TYPES[$query->type]);
        }

        if ([] !== $query->accept) {
            $this->andWhereMimeTypeLike($qb, 'accept', array_map(MimeTypePatterns::toLike(...), $query->accept));
        }

        if ($query->createdFrom instanceof \DateTimeImmutable) {
            $qb->andWhere('f.createdAt >= :from')->setParameter('from', $query->createdFrom);
        }

        if ($query->createdTo instanceof \DateTimeImmutable) {
            $qb->andWhere('f.createdAt <= :to')->setParameter('to', $query->createdTo);
        }

        if ($query->orphans && [] !== $usedIds) {
            $qb->andWhere('f.id NOT IN (:used)')->setParameter('used', $usedIds);
        }

        if ($query->duplicates) {
            $this->andWhereDuplicated($qb);
        }

        $this->orderLibrary($qb, $query);
        $qb->setFirstResult((max(1, $query->page) - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage);

        $paginator = new DoctrinePaginator($qb, fetchJoinCollection: false);
        /** @var list<FileInterface> $items */
        $items = iterator_to_array($paginator->getIterator(), false);

        return new Paginator($items, \count($paginator), max(1, $query->page), $itemsPerPage);
    }

    /**
     * @param list<string> $patterns SQL LIKE patterns, one of them must match
     */
    private function andWhereMimeTypeLike(QueryBuilder $qb, string $parameter, array $patterns): void
    {
        $conditions = [];

        foreach ($patterns as $index => $pattern) {
            $conditions[] = 'f.mimeType LIKE :' . $parameter . $index;
            $qb->setParameter($parameter . $index, $pattern);
        }

        $qb->andWhere(implode(' OR ', $conditions));
    }

    private function andWhereDuplicated(QueryBuilder $qb): void
    {
        $duplicates = $this->createQueryBuilder('d')
            ->select('d.hash')
            ->where('d.hash IS NOT NULL')
            ->groupBy('d.hash')
            ->having('COUNT(d.id) > 1');
        $qb->andWhere($qb->expr()->in('f.hash', $duplicates->getDQL()));
    }

    private function orderLibrary(QueryBuilder $qb, LibraryQuery $query): void
    {
        $direction = 'desc' === strtolower($query->sort) ? 'DESC' : 'ASC';
        $sort = LibraryQuery::SORTS[$query->sortBy] ?? 'originalName';

        // Case insensitive names, as with the MySQL collations.
        if ('originalName' === $sort) {
            $qb->addSelect('LOWER(f.originalName) AS HIDDEN sortName')->orderBy('sortName', $direction);
        } else {
            $qb->orderBy('f.' . $sort, $direction);
        }

        $qb->addOrderBy('f.id', $direction);
    }

    /**
     * "dir" matches "dir/..." (LIKE with "!" as escape character).
     */
    private function likePrefix(string $directory): string
    {
        return $this->escapeLike($directory) . '/%';
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
