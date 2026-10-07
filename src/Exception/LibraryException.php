<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Exception;

final class LibraryException extends \RuntimeException implements TranslatableExceptionInterface
{
    use TranslatableExceptionTrait;

    private const string PATH = '%path%';

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
        return new self(\sprintf('The directory "%s" is not empty.', $path))->translated('error.directory_not_empty', [self::PATH => $path]);
    }

    public static function rootDirectory(): self
    {
        return new self('The library root cannot be removed.')->translated('error.root_directory');
    }
}
