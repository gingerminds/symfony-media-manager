<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Entity\Media;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Fields of the media API, as in Laravel, derived from the file, the thumbnail and the category of BaseMedia.
 */
trait MediaApiFieldsTrait
{
    /**
     * Always the id, never the path (Laravel sent the path of a non-image file).
     */
    #[Groups([BaseMedia::GROUP_LIST, BaseMedia::GROUP_READ])]
    #[SerializedName('file')]
    public function getFileId(): ?string
    {
        return $this->file?->getId();
    }

    #[Groups([BaseMedia::GROUP_LIST, BaseMedia::GROUP_READ])]
    #[SerializedName('file_reference')]
    public function getFileReference(): ?string
    {
        return $this->getFileId();
    }

    #[Groups([BaseMedia::GROUP_LIST, BaseMedia::GROUP_READ])]
    #[SerializedName('file_size')]
    public function getFileSize(): ?int
    {
        return $this->file?->getSize();
    }

    #[Groups([BaseMedia::GROUP_LIST, BaseMedia::GROUP_READ])]
    #[SerializedName('file_type')]
    public function getFileType(): ?string
    {
        return $this->file?->getMimeType();
    }

    #[Groups([BaseMedia::GROUP_LIST, BaseMedia::GROUP_READ])]
    #[SerializedName('thumbnail_reference')]
    public function getThumbnailReference(): ?string
    {
        return $this->thumbnail?->getId();
    }

    #[Groups([BaseMedia::GROUP_LIST, BaseMedia::GROUP_READ])]
    #[SerializedName('thumbnail_size')]
    public function getThumbnailSize(): ?int
    {
        return $this->thumbnail?->getSize();
    }

    #[Groups([BaseMedia::GROUP_LIST, BaseMedia::GROUP_READ])]
    #[SerializedName('media_category_id')]
    public function getCategoryId(): ?int
    {
        return $this->category?->getId();
    }
}
