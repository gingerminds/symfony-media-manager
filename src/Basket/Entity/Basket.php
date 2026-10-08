<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\MediaManagerBundle\Basket\Repository\BasketRepository;

#[ApiResource(
    shortName: 'Basket',
    operations: [
        new Post(
            uriTemplate: '/baskets',
            openapi: new Operation(
                summary: 'Create a basket',
                description: 'A guest basket, or the basket of the authenticated user (replacing the previous one). '
                    . '`anonymous_token` claims a guest basket after login (basket.claim_strategy).',
                requestBody: new RequestBody(content: new \ArrayObject(['application/json' => ['schema' => [
                    'type' => 'object',
                    'properties' => ['anonymous_token' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true]],
                ]]]), required: false),
            ),
            input: false,
            processor: 'gingerminds_media_manager.basket.processor.create',
        ),
        new Get(
            uriTemplate: '/baskets/{token}',
            uriVariables: ['token' => new Link(fromClass: Basket::class, identifiers: ['token'])],
            openapi: new Operation(summary: 'Get a basket', description: 'Guest baskets are public, user baskets need their owner.'),
        ),
        new Delete(
            uriTemplate: '/baskets/{token}',
            uriVariables: ['token' => new Link(fromClass: Basket::class, identifiers: ['token'])],
            processor: 'gingerminds_media_manager.basket.processor.delete',
        ),
        new Post(
            uriTemplate: '/baskets/{token}/medias',
            uriVariables: ['token' => new Link(fromClass: Basket::class, identifiers: ['token'])],
            status: 200,
            openapi: new Operation(
                summary: 'Add medias to a basket',
                description: 'The medias already in the basket are not duplicated.',
                requestBody: new RequestBody(content: new \ArrayObject(['application/json' => ['schema' => [
                    'type' => 'object',
                    'required' => ['media_ids'],
                    'properties' => ['media_ids' => ['type' => 'array', 'items' => ['type' => 'integer']]],
                ]]])),
            ),
            input: false,
            processor: 'gingerminds_media_manager.basket.processor.add_medias',
        ),
        new Delete(
            uriTemplate: '/baskets/{token}/medias/{mediaId}',
            uriVariables: ['token' => new Link(fromClass: Basket::class, identifiers: ['token']), 'mediaId'],
            status: 200,
            openapi: new Operation(summary: 'Remove a media from a basket', description: 'Returns the basket.'),
            output: Basket::class,
            processor: 'gingerminds_media_manager.basket.processor.remove_media',
        ),
        new Get(
            uriTemplate: '/baskets/{token}/download',
            uriVariables: ['token' => new Link(fromClass: Basket::class, identifiers: ['token'])],
            controller: 'gingerminds_media_manager.basket.controller.download',
            openapi: new Operation(
                summary: 'Download a basket as a ZIP',
                description: 'The files of the medias, then the basket is deleted. 422 when there is nothing to download.',
            ),
            output: false,
            read: false,
            name: 'gingerminds_media_manager_basket_download',
        ),
    ],
    normalizationContext: ['groups' => [BaseBasket::GROUP_READ], 'skip_null_values' => false],
    provider: 'gingerminds_media_manager.basket.provider',
)]
#[ORM\Entity(repositoryClass: BasketRepository::class)]
#[ORM\Table(name: 'baskets')]
class Basket extends BaseBasket
{
}
