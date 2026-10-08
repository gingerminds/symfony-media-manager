<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Media;

use Doctrine\Common\Collections\Collection;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaLinkInterface;

/**
 * Applies a list of medias to one collection of the links of an entity: the other collections are untouched.
 */
final class MediaCollectionSyncer
{
    /**
     * @template T of MediaLinkInterface
     *
     * @param Collection<array-key, T>            $links   the owning collection (orphanRemoval deletes the removed links)
     * @param iterable<MediaInterface>            $medias  in their order
     * @param callable(MediaInterface, string): T $factory new link of a media in the collection
     */
    public static function sync(Collection $links, iterable $medias, string|\BackedEnum $collection, callable $factory): void
    {
        $collection = self::name($collection);
        $existing = [];

        foreach ($links as $key => $link) {
            if ($link->getCollection() !== $collection) {
                continue;
            }

            $existing[self::key($link->getMedia())] ??= $link;

            if ($existing[self::key($link->getMedia())] !== $link) {
                $links->remove($key);
            }
        }

        $position = 0;
        $kept = [];

        foreach ($medias as $media) {
            $key = self::key($media);

            if (isset($kept[$key])) {
                continue;
            }

            $link = $existing[$key] ?? $factory($media, $collection);
            $link->setPosition($position++);
            $kept[$key] = $link;

            if (!$links->contains($link)) {
                $links->add($link);
            }
        }

        foreach ($existing as $key => $link) {
            if (!isset($kept[$key])) {
                $links->removeElement($link);
            }
        }
    }

    /**
     * The medias of one collection, by position.
     *
     * @param iterable<MediaLinkInterface> $links
     *
     * @return list<MediaInterface>
     */
    public static function medias(iterable $links, string|\BackedEnum $collection): array
    {
        $collection = self::name($collection);
        $selected = array_values(array_filter([...$links], static fn (MediaLinkInterface $link): bool => $link->getCollection() === $collection));
        usort($selected, static fn (MediaLinkInterface $a, MediaLinkInterface $b): int => $a->getPosition() <=> $b->getPosition());

        return array_map(static fn (MediaLinkInterface $link): MediaInterface => $link->getMedia(), $selected);
    }

    public static function name(string|\BackedEnum $collection): string
    {
        return $collection instanceof \BackedEnum ? (string) $collection->value : $collection;
    }

    private static function key(MediaInterface $media): string
    {
        return null === $media->getId() ? 'object-' . spl_object_id($media) : (string) $media->getId();
    }
}
