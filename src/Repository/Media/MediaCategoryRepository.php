<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Repository\Media;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Repository\AbstractRepository;
use Gingerminds\CoreBundle\Repository\TreeRepositoryInterface;
use Gingerminds\CoreBundle\Repository\TreeRepositoryTrait;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;

/**
 * @extends AbstractRepository<MediaCategoryInterface>
 */
class MediaCategoryRepository extends AbstractRepository implements TreeRepositoryInterface
{
    use TreeRepositoryTrait;

    protected int $itemsPerPage = 200;
    protected int $maxItemsPerPage = 500;

    /**
     * @param class-string<MediaCategoryInterface> $entityClass
     */
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, $entityClass);
    }

    /**
     * Root categories, with every level of children loaded by a single query.
     *
     * @return list<MediaCategoryInterface>
     */
    public function findTree(): array
    {
        /** @var list<MediaCategoryInterface> $categories */
        $categories = $this->createQueryBuilder('c')
            ->addSelect('children')
            ->leftJoin('c.children', 'children')
            ->orderBy('c.position')
            ->addOrderBy('c.id')
            ->addOrderBy('children.position')
            ->addOrderBy('children.id')
            ->getQuery()
            ->getResult();

        return array_values(array_filter($categories, static fn (MediaCategoryInterface $category): bool => !$category->getParent() instanceof MediaCategoryInterface));
    }

    /**
     * @param list<string> $codes
     *
     * @return list<MediaCategoryInterface>
     */
    public function findByCodes(array $codes): array
    {
        return [] === $codes ? [] : array_values($this->findBy(['code' => $codes]));
    }
}
