<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Form\Media;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaLinkInterface;
use Gingerminds\MediaManagerBundle\Media\MediaCollectionSyncer;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * The links of an entity <=> the medias of one of their collections. Keeps the links collection
 * it was given, to sync it on submit.
 *
 * @implements DataTransformerInterface<Collection<array-key, MediaLinkInterface>, Collection<int, MediaInterface>>
 */
final class MediaCollectionTransformer implements DataTransformerInterface
{
    /**
     * @var Collection<array-key, MediaLinkInterface>|null
     */
    private ?Collection $links = null;

    /**
     * @param \Closure(MediaInterface, string): MediaLinkInterface $factory
     */
    public function __construct(
        private readonly string|\BackedEnum $collection,
        private readonly \Closure $factory,
    ) {
    }

    public function transform(mixed $value): Collection
    {
        $this->links = $value instanceof Collection ? $value : null;

        return new ArrayCollection($this->links instanceof Collection ? MediaCollectionSyncer::medias($this->links, $this->collection) : []);
    }

    public function reverseTransform(mixed $value): Collection
    {
        $links = $this->links ?? new ArrayCollection();
        MediaCollectionSyncer::sync($links, is_iterable($value) ? $value : [], $this->collection, $this->factory);

        return $links;
    }
}
