<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Entity\Media;

use Gingerminds\CoreBundle\Model\ResourceInterface;
use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;

interface MediaInterface extends ResourceInterface, TimestampableInterface, \Stringable
{
    public function getId(): ?int;

    public function getCode(): ?string;

    public function setCode(string $code): void;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getFile(): ?FileInterface;

    public function setFile(?FileInterface $file): void;

    public function getThumbnail(): ?FileInterface;

    public function setThumbnail(?FileInterface $thumbnail): void;

    public function getCategory(): ?MediaCategoryInterface;

    public function setCategory(?MediaCategoryInterface $category): void;

    /**
     * The file itself when it is an image, otherwise the thumbnail.
     */
    public function getPreview(): ?FileInterface;
}
