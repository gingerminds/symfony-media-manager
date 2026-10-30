<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Entity\Media;

use Gingerminds\CoreBundle\Model\ResourceInterface;
use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\CoreBundle\Model\TreeNodeInterface;

interface MediaCategoryInterface extends ResourceInterface, TimestampableInterface, TreeNodeInterface, \Stringable
{
    public function getId(): ?int;

    public function getCode(): ?string;

    public function setCode(string $code): void;

    public function getName(): ?string;

    public function setName(string $name): void;

    public function getParent(): ?self;

    public function setParent(?self $parent): void;

    public function getParentId(): ?int;

    /**
     * @return list<self> ordered by position
     */
    public function getChildren(): array;

    public function hasChildren(): bool;
}
