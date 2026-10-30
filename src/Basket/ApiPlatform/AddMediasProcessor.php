<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Gingerminds\MediaManagerBundle\Basket\ApiPlatform\Input\AddMediasInput;
use Gingerminds\MediaManagerBundle\Basket\Entity\BasketInterface;
use Gingerminds\MediaManagerBundle\Basket\Repository\BasketRepository;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaRepository;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * The medias already in the basket are not duplicated.
 *
 * @implements ProcessorInterface<AddMediasInput, BasketInterface>
 */
final readonly class AddMediasProcessor implements ProcessorInterface
{
    public function __construct(
        private BasketRepository $baskets,
        private MediaRepository $medias,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BasketInterface
    {
        $basket = $context['read_data'] ?? null;

        if (!$basket instanceof BasketInterface) {
            throw new \LogicException('The basket provider did not provide a basket.');
        }

        $ids = array_values(array_unique(array_map(intval(...), $data->mediaIds)));
        $medias = $this->medias->findBy(['id' => $ids]);

        if (\count($medias) !== \count($ids)) {
            throw new UnprocessableEntityHttpException('Some medias do not exist.');
        }

        array_map($basket->addMedia(...), $medias);
        $this->baskets->save($basket);

        return $basket;
    }
}
