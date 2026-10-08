<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\Entity;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;

interface BasketInterface extends TimestampableInterface
{
    public function getId(): ?int;

    public function getToken(): string;

    /**
     * Null: a guest basket, open to whoever has its token.
     */
    public function getOwner(): ?UserInterface;

    public function setOwner(?UserInterface $owner): void;

    /**
     * Guest baskets only: the basket of a user never expires.
     */
    public function getExpiresAt(): ?\DateTimeImmutable;

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): void;

    /**
     * @return list<MediaInterface>
     */
    public function getMedias(): array;

    public function hasMedia(MediaInterface $media): bool;

    public function addMedia(MediaInterface $media): void;

    public function removeMedia(MediaInterface $media): void;
}
