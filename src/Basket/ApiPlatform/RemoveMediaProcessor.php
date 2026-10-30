<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Gingerminds\MediaManagerBundle\Basket\Entity\BasketInterface;
use Gingerminds\MediaManagerBundle\Basket\Repository\BasketRepository;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;

/**
 * @implements ProcessorInterface<BasketInterface, BasketInterface>
 */
final readonly class RemoveMediaProcessor implements ProcessorInterface
{
    public function __construct(
        private BasketRepository $baskets,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BasketInterface
    {
        $mediaId = (int) ($uriVariables['mediaId'] ?? 0);

        foreach ($data->getMedias() as $media) {
            if ($media instanceof MediaInterface && $media->getId() === $mediaId) {
                $data->removeMedia($media);
            }
        }

        $this->baskets->save($data);

        return $data;
    }
}
