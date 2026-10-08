<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Exception;

final class ZipArchiveException extends \RuntimeException
{
    public static function couldNotCreate(string $path): self
    {
        return new self(\sprintf('The ZIP archive "%s" could not be created.', $path));
    }

    public static function couldNotClose(string $path): self
    {
        return new self(\sprintf('The ZIP archive "%s" could not be written.', $path));
    }
}
