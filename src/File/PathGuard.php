<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

use Gingerminds\MediaManagerBundle\Exception\InvalidPathException;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Normalizes user given paths and confines them to the library root.
 */
final readonly class PathGuard
{
    public function __construct(
        private string $libraryRoot,
        private SluggerInterface $slugger,
    ) {
    }

    /**
     * "a//b/" becomes "a/b"; absolute paths, "." / ".." segments, backslashes and control characters are rejected.
     */
    public function normalize(string $path): string
    {
        if (1 === preg_match('/[\x00-\x1F\x7F\\\\]/', $path)) {
            throw InvalidPathException::forbiddenCharacters($path);
        }

        if (str_starts_with($path, '/')) {
            throw InvalidPathException::absolute($path);
        }

        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => '' !== $segment));

        if (\in_array('.', $segments, true) || \in_array('..', $segments, true)) {
            throw InvalidPathException::traversal($path);
        }

        return implode('/', $segments);
    }

    public function root(): string
    {
        return $this->normalize($this->libraryRoot);
    }

    /**
     * A directory relative to the library root ("/fr" is "fr"), as a path on the disk.
     */
    public function inLibrary(string $directory): string
    {
        $directory = $this->normalize(ltrim($directory, '/'));

        return '' === $directory ? $this->root() : $this->root() . '/' . $directory;
    }

    /**
     * "Actualités 2026" becomes "actualites-2026".
     */
    public function directoryName(string $name): string
    {
        $name = strtolower($this->slugger->slug($name)->toString());

        if ('' === $name) {
            throw InvalidPathException::forbiddenCharacters($name);
        }

        return $name;
    }

    /**
     * The path relative to the library root of a path on the disk.
     */
    public function relative(string $path): string
    {
        $root = $this->root();

        if ('' === $root || !str_starts_with($path . '/', $root . '/')) {
            return $path;
        }

        return ltrim(substr($path, \strlen($root)), '/');
    }

    /**
     * "Mon Fichier été.PDF" becomes "mon-fichier-ete.pdf".
     */
    public function fileName(string $originalName): string
    {
        $extension = strtolower((string) preg_replace('/[^a-z0-9]/i', '', pathinfo($originalName, \PATHINFO_EXTENSION)));
        $name = strtolower($this->slugger->slug(pathinfo($originalName, \PATHINFO_FILENAME))->toString());

        return ('' === $name ? 'file' : $name) . ('' === $extension ? '' : '.' . $extension);
    }
}
