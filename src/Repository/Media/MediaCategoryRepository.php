<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Repository\Media;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Repository\AbstractRepository;
use Gingerminds\CoreBundle\Repository\ListQuery;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * @extends AbstractRepository<MediaCategoryInterface>
 */
class MediaCategoryRepository extends AbstractRepository
{
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
     * Applies the order of one level of the tree: ids of other levels are ignored.
     *
     * @param list<int> $ids
     */
    public function reorder(?int $parentId, array $ids): void
    {
        $siblings = [];

        foreach ($this->findBy(['parent' => $parentId]) as $category) {
            $siblings[(int) $category->getId()] = $category;
        }

        foreach (array_values($ids) as $position => $id) {
            if (isset($siblings[$id])) {
                $siblings[$id]->setPosition($position);
            }
        }

        $this->getEntityManager()->flush();
    }

    protected function configureListQueryBuilder(QueryBuilder $qb, ListQuery $query): void
    {
        if (null === $query->sortBy) {
            $qb->addOrderBy(self::ALIAS . '.position');
        }
    }

    /**
     * A new category, or one moved to another parent, goes to the end of its level.
     */
    protected function beforeSave(object $entity, ?FormInterface $form): void
    {
        $original = $this->getEntityManager()->getUnitOfWork()->getOriginalEntityData($entity);

        if ([] !== $original && ($original['parent'] ?? null) === $entity->getParent()) {
            return;
        }

        $qb = $this->createQueryBuilder('c')->select('MAX(c.position)');
        $parent = $entity->getParent();

        if (null === $parent) {
            $qb->where('c.parent IS NULL');
        } else {
            $qb->where('c.parent = :parent')->setParameter('parent', $parent);
        }

        if (null !== $entity->getId()) {
            $qb->andWhere('c.id <> :id')->setParameter('id', $entity->getId());
        }

        $max = $qb->getQuery()->getSingleScalarResult();
        $entity->setPosition(null === $max ? 0 : (int) $max + 1);
    }
}
