<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Gingerminds\MediaManagerBundle\Basket\Entity\BasketInterface;
use Gingerminds\MediaManagerBundle\Basket\Repository\BasketRepository;
use Gingerminds\MediaManagerBundle\Basket\Security\BasketVoter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The basket of the `token` URI variable: 404 when unknown or expired, 403 when another user owns it.
 *
 * @implements ProviderInterface<BasketInterface>
 */
final readonly class BasketProvider implements ProviderInterface
{
    public function __construct(
        private BasketRepository $baskets,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BasketInterface
    {
        $token = $uriVariables['token'] ?? null;
        $basket = \is_string($token) ? $this->baskets->findOneByToken($token) : null;

        if (!$basket instanceof BasketInterface) {
            throw new NotFoundHttpException('Basket not found.');
        }

        if (!$this->authorizationChecker->isGranted(BasketVoter::VIEW, $basket)) {
            throw new AccessDeniedHttpException('This basket belongs to another user.');
        }

        return $basket;
    }
}
