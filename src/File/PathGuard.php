<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

use Gingerminds\MediaManagerBundle\Exception\InvalidPathException;

/**
 * Normalizes user given paths and confines them to the library root; the names come from the
 * FileNamerInterface.
 */
final readonly class PathGuard
{
    public function __construct(
        private string $libraryRoot,
        private FileNamerInterface $namer,
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
     * A single segment: a namer returning "a/b" or ".." is refused.
     */
    public function directoryName(string $name): string
    {
        $name = $this->namer->directoryName($name);

        if ('' === $name || $name !== $this->normalize($name) || str_contains($name, '/')) {
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
     * A single segment: a namer returning "a/b" or ".." is refused.
     */
    public function fileName(string $originalName): string
    {
        $name = $this->namer->fileName($originalName);

        if ('' === $name || $name !== $this->normalize($name) || str_contains($name, '/')) {
            throw InvalidPathException::forbiddenCharacters($name);
        }

        return $name;
    }
}
