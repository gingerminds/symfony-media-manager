<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File\Reference;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;

/**
 * File ids stored in a JSON (or text) field, such as content blocks. No database integrity: the
 * ids are searched as strings, which is reliable for UUIDs.
 */
class JsonFieldFileReferenceSource implements FileReferenceSourceInterface
{
    private const string UUID_PATTERN = '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i';

    /**
     * @param class-string $entityClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $entityClass,
        private readonly string $field,
    ) {
    }

    public function references(array $ids): array
    {
        $references = [];

        foreach ($this->owners($ids) as $owner) {
            $content = $this->content($owner);

            foreach ($ids as $id) {
                if (str_contains($content, $id)) {
                    $references[] = new FileReference($id, $owner, $this->field);
                }
            }
        }

        return $references;
    }

    public function usedIds(): array
    {
        $ids = [];
        $values = $this->entityManager->createQueryBuilder()
            ->select('o.' . $this->field . ' AS value')
            ->from($this->entityClass, 'o')
            ->where('o.' . $this->field . ' IS NOT NULL')
            ->getQuery()
            ->toIterable();

        foreach ($values as $row) {
            preg_match_all(self::UUID_PATTERN, $this->encode($row['value']), $matches);

            foreach ($matches[0] as $id) {
                $ids[strtolower($id)] = true;
            }
        }

        return array_map(strval(...), array_keys($ids));
    }

    public function replace(FileInterface $old, FileInterface $new): int
    {
        $metadata = $this->entityManager->getClassMetadata($this->entityClass);
        $owners = $this->owners([$old->getId()]);

        foreach ($owners as $owner) {
            $value = $metadata->getFieldValue($owner, $this->field);
            $replaced = str_replace($old->getId(), $new->getId(), $this->encode($value));
            $metadata->setFieldValue($owner, $this->field, \is_string($value) ? $replaced : json_decode($replaced, true, flags: \JSON_THROW_ON_ERROR));
        }

        $this->entityManager->flush();

        return \count($owners);
    }

    /**
     * @param list<string> $ids
     *
     * @return list<object>
     */
    private function owners(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $qb = $this->entityManager->createQueryBuilder()->select('o')->from($this->entityClass, 'o');

        foreach (array_values($ids) as $index => $id) {
            $qb->orWhere('o.' . $this->field . ' LIKE :id' . $index)->setParameter('id' . $index, '%' . $id . '%');
        }

        /** @var list<object> */
        return $qb->getQuery()->getResult();
    }

    private function content(object $owner): string
    {
        return $this->encode($this->entityManager->getClassMetadata($this->entityClass)->getFieldValue($owner, $this->field));
    }

    private function encode(mixed $value): string
    {
        return \is_string($value) ? $value : json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }
}
