<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File\Reference;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;

/**
 * Every Doctrine association to a file, found in the mapping: none can be forgotten.
 */
final class DoctrineAssociationReferenceSource implements FileReferenceSourceInterface
{
    /**
     * @var list<array{ClassMetadata<object>, string}>|null class and field
     */
    private ?array $associations = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function references(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $references = [];

        foreach ($this->associations() as [$metadata, $field]) {
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

    public function usedIds(): array
    {
        $ids = [];

        foreach ($this->associations() as [$metadata, $field]) {
            $rows = $this->entityManager->createQueryBuilder()
                ->select('DISTINCT f.id')
                ->from($metadata->getName(), 'o')
                ->join('o.' . $field, 'f')
                ->getQuery()
                ->getSingleColumnResult();

            foreach ($rows as $id) {
                $ids[(string) $id] = true;
            }
        }

        return array_map(strval(...), array_keys($ids));
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

    /**
     * Owning sides only, once per class hierarchy.
     *
     * @return list<array{ClassMetadata<object>, string}>
     */
    private function associations(): array
    {
        if (null !== $this->associations) {
            return $this->associations;
        }

        $this->associations = [];

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass) {
                continue;
            }

            foreach ($metadata->associationMappings as $field => $mapping) {
                if (!$mapping->isOwningSide() || null !== $mapping->inherited || !is_a($mapping->targetEntity, FileInterface::class, true)) {
                    continue;
                }

                $this->associations[] = [$metadata, $field];
            }
        }

        return $this->associations;
    }
}
