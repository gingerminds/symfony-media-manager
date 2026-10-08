<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Media;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;

/**
 * Rows referencing a media through a Doctrine association (media links, a project ManyToOne...),
 * found in the mapping: a used media cannot be deleted.
 */
class MediaUsageCounter
{
    /**
     * @var list<array{class-string, string}>|null class and field
     */
    private ?array $associations = null;

    /**
     * @param list<class-string> $ignored entities whose references do not count (e.g. the baskets)
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly array $ignored = [],
    ) {
    }

    public function count(MediaInterface $media): int
    {
        if (null === $media->getId()) {
            return 0;
        }

        $count = 0;

        foreach ($this->associations() as [$class, $field]) {
            $count += (int) $this->entityManager->createQueryBuilder()
                ->select('COUNT(o)')
                ->from($class, 'o')
                ->join('o.' . $field, 'm')
                ->where('m.id = :id')
                ->setParameter('id', $media->getId())
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $count;
    }

    /**
     * Owning sides only, once per class hierarchy.
     *
     * @return list<array{class-string, string}>
     */
    private function associations(): array
    {
        if (null !== $this->associations) {
            return $this->associations;
        }

        $this->associations = [];

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $ignored = array_any($this->ignored, static fn (string $class): bool => is_a($metadata->getName(), $class, true));

            if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass || $ignored) {
                continue;
            }

            foreach ($metadata->associationMappings as $field => $mapping) {
                if ($mapping->isOwningSide() && null === $mapping->inherited && is_a($mapping->targetEntity, MediaInterface::class, true)) {
                    $this->associations[] = [$metadata->getName(), $field];
                }
            }
        }

        return $this->associations;
    }
}
