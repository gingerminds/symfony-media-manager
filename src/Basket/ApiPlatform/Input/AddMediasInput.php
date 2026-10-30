<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\ApiPlatform\Input;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /api/baskets/{token}/medias.
 */
final class AddMediasInput
{
    /**
     * @var list<int|string>
     */
    #[SerializedName('media_ids')]
    #[Assert\NotBlank]
    #[Assert\All([new Assert\Type('scalar'), new Assert\Regex('/^[1-9]\d*$/')])]
    public array $mediaIds = [];
}
