<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Exception;

final class InvalidPathException extends \InvalidArgumentException implements TranslatableExceptionInterface
{
    use TranslatableExceptionTrait;

    public static function absolute(string $path): self
    {
        return new self(\sprintf('The path "%s" must be relative.', $path))->translated('error.invalid_path');
    }

    public static function traversal(string $path): self
    {
        return new self(\sprintf('The path "%s" must not contain "." or ".." segments.', $path))->translated('error.invalid_path');
    }

    public static function forbiddenCharacters(string $path): self
    {
        return new self(\sprintf('The path "%s" contains forbidden characters.', addcslashes($path, "\0..\37\177")))->translated('error.invalid_path');
    }
}
