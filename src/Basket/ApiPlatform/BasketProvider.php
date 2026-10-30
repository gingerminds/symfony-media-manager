<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Gingerminds\MediaManagerBundle\Basket\Entity\BasketInterface;
use Gingerminds\MediaManagerBundle\Basket\Repository\BasketRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The basket of the `token` URI variable: 404 when unknown or expired (the operations check the owner).
 *
 * @implements ProviderInterface<BasketInterface>
 */
final readonly class BasketProvider implements ProviderInterface
{
    public function __construct(
        private BasketRepository $baskets,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BasketInterface
    {
        $token = $uriVariables['token'] ?? null;
        $basket = \is_string($token) ? $this->baskets->findOneByToken($token) : null;

        if (!$basket instanceof BasketInterface) {
            throw new NotFoundHttpException('Basket not found.');
        }

        return $basket;
    }
}
