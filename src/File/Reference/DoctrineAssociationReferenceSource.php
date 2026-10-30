<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File\Reference;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Gingerminds\MediaManagerBundle\Doctrine\AssociationFinder;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;

/**
 * Every Doctrine association to a file, found in the mapping: none can be forgotten.
 */
final readonly class DoctrineAssociationReferenceSource implements FileReferenceSourceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AssociationFinder $associations,
    ) {
    }

    public function references(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $references = [];

        foreach ($this->associations->owningAssociationsTo(FileInterface::class) as [$metadata, $field]) {
            $rows = $this->entityManager->createQueryBuilder()
                ->select('o', 'f.id AS fileId')
                ->from($metadata->getName(), 'o')
                ->join('o.' . $field, 'f')
                ->where('f.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult();

            foreach ($rows as $row) {
                $references[] = new FileReference((string) $row['fileId'], $row[0], $field);
            }
        }

        return $references;
    }

    public function excludeReferenced(QueryBuilder $qb, string $alias): void
    {
        foreach ($this->associations->owningAssociationsTo(FileInterface::class) as $index => [$metadata, $field]) {
            $owner = 'gm_reference_' . $index;
            $subquery = $this->entityManager->createQueryBuilder()
                ->select($owner)
                ->from($metadata->getName(), $owner)
                ->join($owner . '.' . $field, $owner . '_file')
                ->where($owner . '_file = ' . $alias);

            $qb->andWhere($qb->expr()->not($qb->expr()->exists($subquery->getDQL())));
        }
    }

    public function replace(FileInterface $old, FileInterface $new): int
    {
        $updated = 0;

        foreach ($this->references([$old->getId()]) as $reference) {
            $metadata = $this->entityManager->getClassMetadata($reference->owner::class);
            $value = $metadata->getFieldValue($reference->owner, $reference->field);

            if ($value instanceof Collection) {
                $value->removeElement($old);

                if (!$value->contains($new)) {
                    $value->add($new);
                }
            } else {
                $metadata->setFieldValue($reference->owner, $reference->field, $new);
            }

            ++$updated;
        }

        $this->entityManager->flush();

        return $updated;
    }
}
