<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Exception;

final class FileUploadException extends \RuntimeException
{
    public static function notAllowed(string $mimeType): self
    {
        return new self(\sprintf('The file type "%s" is not allowed.', $mimeType));
    }

    public static function tooLarge(int $size, int $maxSizeInKb): self
    {
        return new self(\sprintf('The file is too large (%d KB, %d KB allowed).', (int) ceil($size / 1024), $maxSizeInKb));
    }

    public static function couldNotStore(string $name, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('The file "%s" could not be stored.', $name), previous: $previous);
    }
}
