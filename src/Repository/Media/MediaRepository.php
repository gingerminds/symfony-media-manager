<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Repository\Media;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Repository\AbstractRepository;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;
use Symfony\Component\Form\FormInterface;

/**
 * @extends AbstractRepository<MediaInterface>
 */
class MediaRepository extends AbstractRepository
{
    protected int $itemsPerPage = 25;

    /**
     * @param class-string<MediaInterface> $entityClass
     */
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, $entityClass);
    }

    public function countByCategory(MediaCategoryInterface $category): int
    {
        return $this->count(['category' => $category]);
    }

    /**
     * @param list<int> $ids
     *
     * @return list<MediaInterface>
     */
    public function findWithFiles(array $ids): array
    {
        /** @var list<MediaInterface> */
        return $this->createQueryBuilder('m')
            ->addSelect('f', 't')
            ->join('m.file', 'f')
            ->leftJoin('m.thumbnail', 't')
            ->where('m.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Without a name, the media is named after its file.
     */
    protected function beforeSave(object $entity, ?FormInterface $form): void
    {
        if (null === $entity->getName() && null !== $entity->getFile()) {
            $entity->setName($entity->getFile()->getOriginalName());
        }
    }
}
