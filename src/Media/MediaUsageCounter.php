<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Media;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\MediaManagerBundle\Doctrine\AssociationFinder;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;

/**
 * Rows referencing a media through a Doctrine association (media links, a project ManyToOne...),
 * found in the mapping: a used media cannot be deleted.
 */
class MediaUsageCounter
{
    /**
     * @param list<class-string> $ignored entities whose references do not count (e.g. the baskets)
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AssociationFinder $associations,
        private readonly array $ignored = [],
    ) {
    }

    public function count(MediaInterface $media): int
    {
        if (null === $media->getId()) {
            return 0;
        }

        $count = 0;

        foreach ($this->associations->owningAssociationsTo(MediaInterface::class) as [$metadata, $field]) {
            if (array_any($this->ignored, static fn (string $class): bool => is_a($metadata->getName(), $class, true))) {
                continue;
            }

            $count += (int) $this->entityManager->createQueryBuilder()
                ->select('COUNT(o)')
                ->from($metadata->getName(), 'o')
                ->join('o.' . $field, 'm')
                ->where('m.id = :id')
                ->setParameter('id', $media->getId())
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $count;
    }
}
