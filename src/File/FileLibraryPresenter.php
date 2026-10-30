<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Twig\MediaManagerExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The files as JSON: picked files of the FilePickerType fields, uploads.
 */
class FileLibraryPresenter
{
    public const string THUMBNAIL_PRESET = 'thumbnail';
    public const string PREVIEW_PRESET = 'card';

    /**
     * @param array<string, mixed> $presets
     */
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PathGuard $paths,
        private readonly array $presets,
    ) {
    }

    /**
     * The declared presets, for the previews of the browser.
     *
     * @return list<string>
     */
    public function presets(): array
    {
        return array_map(strval(...), array_keys($this->presets));
    }

    /**
     * @return array<string, mixed>
     */
    public function file(FileInterface $file, ?int $usages = null): array
    {
        $createdAt = $file instanceof TimestampableInterface ? $file->getCreatedAt() : null;
        $directory = \dirname($file->getPath());

        return [
            'id' => $file->getId(),
            'name' => $file->getOriginalName(),
            'alt' => $file->getAlt(),
            'extension' => strtolower(pathinfo($file->getPath(), \PATHINFO_EXTENSION)),
            'mimeType' => $file->getMimeType(),
            'size' => $file->getSize(),
            'isImage' => $file->isImage(),
            'directory' => '.' === $directory ? '' : $this->paths->relative($directory),
            'createdAt' => $createdAt?->format(\DATE_ATOM),
            'url' => $this->urlGenerator->generate(MediaManagerExtension::FILE_ROUTE, ['id' => $file->getId()]),
            'thumbnailUrl' => $this->presetUrl($file, self::THUMBNAIL_PRESET),
            'previewUrl' => $this->presetUrl($file, self::PREVIEW_PRESET),
            'usages' => $usages,
        ];
    }

    /**
     * Null for a non image or a preset the project does not declare.
     */
    private function presetUrl(FileInterface $file, string $preset): ?string
    {
        return $file->isImage() && isset($this->presets[$preset])
            ? $this->urlGenerator->generate(MediaManagerExtension::FILE_PRESET_ROUTE, ['id' => $file->getId(), 'preset' => $preset])
            : null;
    }
}
