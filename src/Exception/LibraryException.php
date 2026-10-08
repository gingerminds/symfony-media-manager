<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Exception;

final class LibraryException extends \RuntimeException implements TranslatableExceptionInterface
{
    use TranslatableExceptionTrait;

    private const string PATH = '%path%';

    private const int NOT_EMPTY = 1;

    public static function directoryNotFound(string $path): self
    {
        return new self(\sprintf('The directory "%s" does not exist.', $path))->translated('error.directory_not_found', [self::PATH => $path]);
    }

    public static function directoryExists(string $path): self
    {
        return new self(\sprintf('The directory "%s" already exists.', $path))->translated('error.directory_exists', [self::PATH => $path]);
    }

    public static function directoryNotEmpty(string $path): self
    {
        return new self(\sprintf('The directory "%s" is not empty.', $path), self::NOT_EMPTY)->translated('error.directory_not_empty', [self::PATH => $path]);
    }

    public function isDirectoryNotEmpty(): bool
    {
        return self::NOT_EMPTY === $this->getCode();
    }

    public static function rootDirectory(): self
    {
        return new self('The library root cannot be removed.')->translated('error.root_directory');
    }

    public static function rootDirectoryMoved(): self
    {
        return new self('The library root cannot be moved nor renamed.')->translated('error.root_directory_moved');
    }

    public static function directoryIntoItself(string $path): self
    {
        return new self(\sprintf('The directory "%s" cannot be moved into itself.', $path))->translated('error.directory_into_itself', [self::PATH => $path]);
    }

    public static function directoryTooLarge(string $path, int $count, int $max): self
    {
        return new self(\sprintf('The directory "%s" holds %d files, more than %d.', $path, $count, $max))
            ->translated('error.directory_too_large', [self::PATH => $path, '%count%' => $count, '%max%' => $max, '%command%' => 'gingerminds:media:directory:move']);
    }
}
