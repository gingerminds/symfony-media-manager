<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Lowercase slugs, the extension kept.
 */
final readonly class SlugFileNamer implements FileNamerInterface
{
    public function __construct(
        private SluggerInterface $slugger,
    ) {
    }

    public function fileName(string $originalName): string
    {
        $extension = strtolower((string) preg_replace('/[^a-z0-9]/i', '', pathinfo($originalName, \PATHINFO_EXTENSION)));
        $name = strtolower($this->slugger->slug(pathinfo($originalName, \PATHINFO_FILENAME))->toString());

        return ('' === $name ? 'file' : $name) . ('' === $extension ? '' : '.' . $extension);
    }

    public function directoryName(string $name): string
    {
        return strtolower($this->slugger->slug($name)->toString());
    }
}
