<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Exception;

final class LibraryException extends \RuntimeException
{
    public static function directoryNotFound(string $path): self
    {
        return new self(\sprintf('The directory "%s" does not exist.', $path));
    }

    public static function directoryExists(string $path): self
    {
        return new self(\sprintf('The directory "%s" already exists.', $path));
    }

    public static function directoryNotEmpty(string $path): self
    {
        return new self(\sprintf('The directory "%s" is not empty.', $path));
    }

    public static function rootDirectory(): self
    {
        return new self('The library root cannot be removed.');
    }
}
