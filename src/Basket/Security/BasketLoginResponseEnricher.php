<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\Security;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Security\Api\LoginResponseEnricherInterface;
use Gingerminds\MediaManagerBundle\Basket\Repository\BasketRepository;

/**
 * `basket_token` of the user basket (created when missing) in the API login response.
 */
final readonly class BasketLoginResponseEnricher implements LoginResponseEnricherInterface
{
    public function __construct(
        private BasketRepository $baskets,
    ) {
    }

    public function enrich(UserInterface $user, array $data): array
    {
        return [...$data, 'basket_token' => $this->baskets->findOrCreateForOwner($user)->getToken()];
    }
}
