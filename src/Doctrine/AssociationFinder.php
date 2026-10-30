<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * The associations to an entity found in the Doctrine mapping (the references to a file, a media):
 * owning sides only, once per class hierarchy.
 */
final class AssociationFinder
{
    /**
     * @var array<string, list<array{ClassMetadata<object>, string}>> class and field, by target
     */
    private array $associations = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param class-string $target an entity class or interface
     *
     * @return list<array{ClassMetadata<object>, string}> class and field
     */
    public function owningAssociationsTo(string $target): array
    {
        return $this->associations[$target] ??= $this->find($target);
    }

    /**
     * @param class-string $target
     *
     * @return list<array{ClassMetadata<object>, string}>
     */
    private function find(string $target): array
    {
        $associations = [];

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass) {
                continue;
            }

            foreach ($metadata->associationMappings as $field => $mapping) {
                if ($mapping->isOwningSide() && null === $mapping->inherited && is_a($mapping->targetEntity, $target, true)) {
                    $associations[] = [$metadata, $field];
                }
            }
        }

        return $associations;
    }
}
