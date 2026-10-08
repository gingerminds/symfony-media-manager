<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Form\Media;

use Doctrine\Common\Collections\ArrayCollection;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaRepository;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Medias (or their ids with `as_id`) <=> the comma separated ids of the hidden input.
 *
 * @implements DataTransformerInterface<mixed, string>
 */
final readonly class MediaSelectTransformer implements DataTransformerInterface
{
    /**
     * @param list<int>|null $categoryIds allowed categories, null: every media
     */
    public function __construct(
        private MediaRepository $medias,
        private bool $multiple,
        private bool $asId,
        private ?array $categoryIds,
    ) {
    }

    public function transform(mixed $value): string
    {
        $values = is_iterable($value) ? [...$value] : [$value];

        return implode(',', array_filter(array_map(
            static fn (mixed $media): ?string => match (true) {
                $media instanceof MediaInterface => null === $media->getId() ? null : (string) $media->getId(),
                \is_int($media), \is_string($media) => (string) $media,
                default => null,
            },
            $values,
        ), static fn (?string $id): bool => null !== $id && '' !== $id));
    }

    public function reverseTransform(mixed $value): mixed
    {
        $ids = self::ids(\is_string($value) ? $value : '');

        if (!$this->multiple && \count($ids) > 1) {
            throw new TransformationFailedException('A single media is expected.');
        }

        $medias = $this->find($ids);

        if ($this->multiple) {
            return $this->asId ? array_map(static fn (MediaInterface $media): int => (int) $media->getId(), $medias) : new ArrayCollection($medias);
        }

        if ([] === $medias) {
            return null;
        }

        return $this->asId ? (int) $medias[0]->getId() : $medias[0];
    }

    /**
     * @return list<int>
     */
    public static function ids(string $value): array
    {
        $ids = array_filter(array_map(trim(...), explode(',', $value)), static fn (string $id): bool => '' !== $id);

        foreach ($ids as $id) {
            if (!ctype_digit($id)) {
                throw new TransformationFailedException(\sprintf('"%s" is not a media id.', $id));
            }
        }

        return array_values(array_unique(array_map(intval(...), $ids)));
    }

    /**
     * @param list<int> $ids
     *
     * @return list<MediaInterface> in the order of the ids
     */
    private function find(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $found = [];

        foreach ($this->medias->findBy(['id' => $ids]) as $media) {
            $found[(int) $media->getId()] = $media;
        }

        $medias = [];

        foreach ($ids as $id) {
            $media = $found[$id] ?? null;

            if (null === $media) {
                $failure = new TransformationFailedException(\sprintf('The media "%d" does not exist.', $id));
                $failure->setInvalidMessage('gingerminds_media_manager.media_select.not_found');

                throw $failure;
            }

            if (null !== $this->categoryIds && !\in_array($media->getCategory()?->getId(), $this->categoryIds, true)) {
                $failure = new TransformationFailedException(\sprintf('The media "%d" is not in the allowed categories.', $id));
                $failure->setInvalidMessage('gingerminds_media_manager.media_select.category', ['{{ name }}' => (string) $media]);

                throw $failure;
            }

            $medias[] = $media;
        }

        return $medias;
    }
}
