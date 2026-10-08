<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Entity\Media;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaRepository;

#[ApiResource(
    shortName: 'Media',
    operations: [
        new GetCollection(normalizationContext: ['groups' => [BaseMedia::GROUP_LIST], 'skip_null_values' => false]),
        new Get(),
    ],
    normalizationContext: ['groups' => [BaseMedia::GROUP_READ], 'skip_null_values' => false],
    paginationClientItemsPerPage: true,
    provider: 'gingerminds_media_manager.api.provider.media',
)]
#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'medias')]
class Media extends BaseMedia
{
}
