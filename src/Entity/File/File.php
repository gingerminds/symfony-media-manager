<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Entity\File;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;

#[ApiResource(
    shortName: 'File',
    operations: [
        new Get(
            uriTemplate: '/files/{id}',
            controller: 'gingerminds_media_manager.controller.file.show',
            openapi: new Operation(summary: 'Retrieve a file', description: 'The original file (image, document...).'),
            read: false,
            name: 'gingerminds_media_manager_file',
            extraProperties: ['rate_limiter' => 'gingerminds_media_manager_files'],
        ),
        new Get(
            uriTemplate: '/files/{id}/{preset}',
            uriVariables: ['id'],
            controller: 'gingerminds_media_manager.controller.file.show_preset',
            openapi: new Operation(
                summary: 'Retrieve an image rendered with a preset',
                description: 'The image resized with a configured preset. An SVG is returned as is.',
                parameters: [new Parameter(name: 'preset', in: 'path', description: 'Preset name', required: true, schema: ['type' => 'string'])],
            ),
            read: false,
            name: 'gingerminds_media_manager_file_preset',
            extraProperties: ['rate_limiter' => 'gingerminds_media_manager_files'],
        ),
    ],
)]
#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ORM\Table(name: 'files')]
#[ORM\Index(name: 'files_hash_index', columns: ['hash'])]
class File extends BaseFile
{
}
