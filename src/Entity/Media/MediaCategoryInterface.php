<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Entity\Media;

use Gingerminds\CoreBundle\Model\ResourceInterface;

interface MediaCategoryInterface extends ResourceInterface, \Stringable
{
    public function getId(): ?int;

    public function getCode(): ?string;

    public function setCode(string $code): void;

    public function getName(): ?string;

    public function setName(string $name): void;

    public function getParent(): ?self;

    public function setParent(?self $parent): void;

    public function getParentId(): ?int;

    public function getPosition(): int;

    public function setPosition(int $position): void;

    /**
     * @return list<self> ordered by position
     */
    public function getChildren(): array;

    public function hasChildren(): bool;

    /**
     * Whether the category is this one or one of its descendants.
     */
    public function contains(self $category): bool;
}
