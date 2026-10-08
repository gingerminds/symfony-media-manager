<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Entity\Media;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaCategoryRepository;

#[ApiResource(
    shortName: 'MediaCategory',
    operations: [
        new GetCollection(normalizationContext: ['groups' => [BaseMediaCategory::GROUP_LIST], 'skip_null_values' => false]),
        // Before Get: "tree" is not an id.
        new GetCollection(
            uriTemplate: '/media_categories/tree',
            paginationEnabled: false,
            normalizationContext: ['groups' => [BaseMediaCategory::GROUP_TREE], 'skip_null_values' => false],
            provider: 'gingerminds_media_manager.api.provider.media_category_tree',
        ),
        new Get(),
    ],
    // `parent_id` is null rather than missing, as in Laravel.
    normalizationContext: ['groups' => [BaseMediaCategory::GROUP_READ], 'skip_null_values' => false],
    paginationClientItemsPerPage: true,
    provider: 'gingerminds_media_manager.api.provider.media_category',
)]
#[ORM\Entity(repositoryClass: MediaCategoryRepository::class)]
#[ORM\Table(name: 'media_categories')]
class MediaCategory extends BaseMediaCategory
{
}
