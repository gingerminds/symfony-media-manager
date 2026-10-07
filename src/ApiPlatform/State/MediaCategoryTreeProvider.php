<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaCategoryRepository;

/**
 * Root categories with their nested children.
 *
 * @implements ProviderInterface<MediaCategoryInterface>
 */
final readonly class MediaCategoryTreeProvider implements ProviderInterface
{
    public function __construct(
        private MediaCategoryRepository $categories,
    ) {
    }

    /**
     * @return list<MediaCategoryInterface>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->categories->findTree();
    }
}
