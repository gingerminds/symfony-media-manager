<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Entity\Media;

/**
 * A media in an ordered collection of an entity (e.g. the "visual" medias of a product).
 */
interface MediaLinkInterface
{
    public function getMedia(): MediaInterface;

    public function getCollection(): string;

    public function getPosition(): int;

    public function setPosition(int $position): void;
}
