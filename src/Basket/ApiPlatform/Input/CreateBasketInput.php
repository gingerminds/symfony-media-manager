<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\ApiPlatform\Input;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /api/baskets.
 */
final class CreateBasketInput
{
    /**
     * A guest basket claimed by the authenticated user (basket.claim_strategy).
     */
    #[SerializedName('anonymous_token')]
    #[Assert\Uuid]
    public ?string $anonymousToken = null;
}
