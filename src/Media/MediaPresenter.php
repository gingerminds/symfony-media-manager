<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Media;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;
use Gingerminds\MediaManagerBundle\File\FileLibraryPresenter;

/**
 * JSON of a media in the media picker and in the MediaSelectType widget.
 */
class MediaPresenter
{
    public function __construct(
        private readonly FileLibraryPresenter $files,
    ) {
    }

    /**
     * @return array{id: int|null, code: string|null, name: string, category: string|null, file: array<string, mixed>|null, thumbnailUrl: string|null}
     */
    public function media(MediaInterface $media): array
    {
        $file = $media->getFile();
        $preview = $media->getPreview();
        $previewData = $preview instanceof FileInterface ? $this->files->file($preview) : null;

        return [
            'id' => $media->getId(),
            'code' => $media->getCode(),
            'name' => (string) $media,
            'category' => $media->getCategory()?->getName(),
            'file' => $file instanceof FileInterface ? $this->files->file($file) : null,
            'thumbnailUrl' => $previewData['thumbnailUrl'] ?? $previewData['url'] ?? null,
        ];
    }
}
