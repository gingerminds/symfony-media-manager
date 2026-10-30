<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\MediaManagerBundle\Basket\ApiPlatform\Input\CreateBasketInput;
use Gingerminds\MediaManagerBundle\Basket\Entity\BasketInterface;
use Gingerminds\MediaManagerBundle\Basket\Repository\BasketRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * A guest basket, or the basket of the authenticated user, claiming its guest basket.
 *
 * @implements ProcessorInterface<CreateBasketInput, BasketInterface>
 */
final readonly class CreateBasketProcessor implements ProcessorInterface
{
    public function __construct(
        private BasketRepository $baskets,
        private Security $security,
        private string $claimStrategy,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BasketInterface
    {
        $user = $this->security->getUser();

        if (!$user instanceof UserInterface) {
            return $this->baskets->createGuestBasket();
        }

        $basket = $this->baskets->createForOwner($user);
        $guest = null !== $data->anonymousToken ? $this->baskets->findOneByToken($data->anonymousToken) : null;

        if ($guest instanceof BasketInterface && !$guest->getOwner() instanceof UserInterface) {
            $this->baskets->claim($guest, $basket, $this->claimStrategy);
        }

        return $basket;
    }
}
