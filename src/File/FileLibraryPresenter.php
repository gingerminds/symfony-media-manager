<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\CoreBundle\Pagination\Paginator;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\File\Reference\FileReferenceRegistry;
use Gingerminds\MediaManagerBundle\File\Reference\FileUsage;
use Gingerminds\MediaManagerBundle\Twig\MediaManagerExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * JSON of the library browser.
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
        private readonly FileReferenceRegistry $references,
        private readonly PathGuard $paths,
        private readonly array $presets,
    ) {
    }

    /**
     * @param Paginator<FileInterface>                                    $page
     * @param list<array{name: string, path: string, hasChildren?: bool}> $directories
     *
     * @return array<string, mixed>
     */
    public function browse(string $path, array $directories, Paginator $page): array
    {
        $files = array_values($page->getItems());
        $usages = $this->references->usages($files);

        return [
            'path' => $path,
            'breadcrumb' => $this->breadcrumb($path),
            'directories' => $directories,
            'files' => array_map(fn (FileInterface $file): array => $this->file($file, \count($usages[$file->getId()] ?? [])), $files),
            'pagination' => [
                'page' => $page->getPage(),
                'pages' => $page->getLastPage(),
                'itemsPerPage' => $page->getItemsPerPage(),
                'totalItems' => $page->getTotalItems(),
            ],
        ];
    }

    /**
     * @return array{name: string, path: string, hasChildren?: bool}
     */
    public function directory(string $path, ?bool $hasChildren = null): array
    {
        return ['name' => basename($path), 'path' => $path] + (null === $hasChildren ? [] : ['hasChildren' => $hasChildren]);
    }

    /**
     * @return list<array{name: string, path: string}>
     */
    public function breadcrumb(string $path): array
    {
        $breadcrumb = [];
        $current = '';

        foreach (array_filter(explode('/', $path), static fn (string $segment): bool => '' !== $segment) as $segment) {
            $current = ltrim($current . '/' . $segment, '/');
            $breadcrumb[] = $this->directory($current);
        }

        return $breadcrumb;
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
     * @param list<FileUsage> $usages
     *
     * @return list<array{label: string, title: string, editUrl: string|null}>
     */
    public function usages(array $usages): array
    {
        return array_map(static fn (FileUsage $usage): array => ['label' => $usage->label, 'title' => $usage->title, 'editUrl' => $usage->editUrl], $usages);
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
