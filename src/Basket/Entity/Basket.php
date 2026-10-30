<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\State\SerializerContextBuilderInterface;
use Doctrine\ORM\Mapping as ORM;
use Gingerminds\MediaManagerBundle\Basket\ApiPlatform\AddMediasProcessor;
use Gingerminds\MediaManagerBundle\Basket\ApiPlatform\CreateBasketProcessor;
use Gingerminds\MediaManagerBundle\Basket\ApiPlatform\Input\AddMediasInput;
use Gingerminds\MediaManagerBundle\Basket\ApiPlatform\Input\CreateBasketInput;
use Gingerminds\MediaManagerBundle\Basket\ApiPlatform\RemoveMediaProcessor;
use Gingerminds\MediaManagerBundle\Basket\Repository\BasketRepository;
use Gingerminds\MediaManagerBundle\Basket\Security\BasketVoter;

#[ApiResource(
    shortName: 'Basket',
    operations: [
        new Post(
            uriTemplate: '/baskets',
            openapi: new Operation(
                summary: 'Create a basket',
                description: 'A guest basket, or the basket of the authenticated user (replacing the previous one). '
                    . '`anonymous_token` claims a guest basket after login (basket.claim_strategy).',
            ),
            denormalizationContext: [SerializerContextBuilderInterface::ASSIGN_OBJECT_TO_POPULATE => false],
            input: CreateBasketInput::class,
            processor: CreateBasketProcessor::class,
        ),
        new Get(
            uriTemplate: '/baskets/{token}',
            uriVariables: ['token' => new Link(identifiers: ['token'])],
            openapi: new Operation(summary: 'Get a basket', description: 'Guest baskets are public, user baskets need their owner.'),
            security: self::SECURITY_VIEW,
            securityMessage: self::SECURITY_MESSAGE,
        ),
        new Delete(
            uriTemplate: '/baskets/{token}',
            uriVariables: ['token' => new Link(identifiers: ['token'])],
            security: self::SECURITY_DELETE,
            securityMessage: self::SECURITY_MESSAGE,
        ),
        new Post(
            uriTemplate: '/baskets/{token}/medias',
            uriVariables: ['token' => new Link(identifiers: ['token'])],
            status: 200,
            openapi: new Operation(summary: 'Add medias to a basket', description: 'The medias already in the basket are not duplicated.'),
            denormalizationContext: [SerializerContextBuilderInterface::ASSIGN_OBJECT_TO_POPULATE => false],
            security: self::SECURITY_MODIFY,
            securityMessage: self::SECURITY_MESSAGE,
            input: AddMediasInput::class,
            processor: AddMediasProcessor::class,
        ),
        new Delete(
            uriTemplate: '/baskets/{token}/medias/{mediaId}',
            uriVariables: ['token' => new Link(identifiers: ['token']), 'mediaId'],
            status: 200,
            openapi: new Operation(summary: 'Remove a media from a basket', description: 'Returns the basket.'),
            security: self::SECURITY_MODIFY,
            securityMessage: self::SECURITY_MESSAGE,
            output: Basket::class,
            processor: RemoveMediaProcessor::class,
        ),
        new Get(
            uriTemplate: '/baskets/{token}/download',
            uriVariables: ['token' => new Link(identifiers: ['token'])],
            controller: 'gingerminds_media_manager.basket.controller.download',
            openapi: new Operation(
                summary: 'Download a basket as a ZIP',
                description: 'The files of the medias (the basket is kept, unless basket.delete_after_download). 422 when there is nothing to download.',
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
    private const string SECURITY_VIEW = "is_granted('" . BasketVoter::VIEW . "', object)";
    private const string SECURITY_MODIFY = "is_granted('" . BasketVoter::MODIFY . "', object)";
    private const string SECURITY_DELETE = "is_granted('" . BasketVoter::DELETE . "', object)";
    private const string SECURITY_MESSAGE = 'This basket belongs to another user.';
}
