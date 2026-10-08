<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\MediaManagerBundle\Basket\Entity\BasketInterface;
use Gingerminds\MediaManagerBundle\Basket\Repository\BasketRepository;
use Gingerminds\MediaManagerBundle\Basket\Security\BasketVoter;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Writes of the basket API, one service per `action`: create, delete, add_medias, remove_media.
 *
 * @implements ProcessorInterface<BasketInterface|null, BasketInterface|null>
 */
final readonly class BasketProcessor implements ProcessorInterface
{
    public const string CREATE = 'create';
    public const string DELETE = 'delete';
    public const string ADD_MEDIAS = 'add_medias';
    public const string REMOVE_MEDIA = 'remove_media';

    public function __construct(
        private string $action,
        private BasketRepository $baskets,
        private MediaRepository $medias,
        private Security $security,
        private RequestStack $requestStack,
        private string $claimStrategy,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?BasketInterface
    {
        if (self::CREATE === $this->action) {
            return $this->create();
        }

        if (!$data instanceof BasketInterface) {
            throw new \LogicException('The basket provider did not provide a basket.');
        }

        $this->denyUnlessGranted(self::DELETE === $this->action ? BasketVoter::DELETE : BasketVoter::MODIFY, $data);

        return match ($this->action) {
            self::DELETE => $this->delete($data),
            self::ADD_MEDIAS => $this->addMedias($data),
            default => $this->removeMedia($data, (int) ($uriVariables['mediaId'] ?? 0)),
        };
    }

    private function create(): BasketInterface
    {
        $user = $this->security->getUser();

        if (!$user instanceof UserInterface) {
            return $this->baskets->createGuestBasket();
        }

        $basket = $this->baskets->createForOwner($user);
        $guestToken = $this->payload()['anonymous_token'] ?? null;
        $guest = \is_string($guestToken) ? $this->baskets->findOneByToken($guestToken) : null;

        if ($guest instanceof BasketInterface && !$guest->getOwner() instanceof UserInterface) {
            $this->baskets->claim($guest, $basket, $this->claimStrategy);
        }

        return $basket;
    }

    private function delete(BasketInterface $basket): null
    {
        $this->baskets->delete($basket);

        return null;
    }

    private function addMedias(BasketInterface $basket): BasketInterface
    {
        $ids = $this->payload()['media_ids'] ?? null;
        $ids = \is_array($ids) ? array_map(static fn (mixed $id): int|false => filter_var($id, \FILTER_VALIDATE_INT), $ids) : [];

        if ([] === $ids || \in_array(false, $ids, true)) {
            throw new UnprocessableEntityHttpException('"media_ids" must be a non empty list of media ids.');
        }

        $ids = array_values(array_unique($ids));
        $medias = $this->medias->findBy(['id' => $ids]);

        if (\count($medias) !== \count($ids)) {
            throw new UnprocessableEntityHttpException('Some medias do not exist.');
        }

        array_map($basket->addMedia(...), $medias);
        $this->baskets->save($basket);

        return $basket;
    }

    private function removeMedia(BasketInterface $basket, int $mediaId): BasketInterface
    {
        foreach ($basket->getMedias() as $media) {
            if ($media instanceof MediaInterface && $media->getId() === $mediaId) {
                $basket->removeMedia($media);
            }
        }

        $this->baskets->save($basket);

        return $basket;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        try {
            return $request instanceof Request ? $request->toArray() : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function denyUnlessGranted(string $attribute, BasketInterface $basket): void
    {
        if (!$this->security->isGranted($attribute, $basket)) {
            throw new AccessDeniedHttpException('This basket belongs to another user.');
        }
    }
}
