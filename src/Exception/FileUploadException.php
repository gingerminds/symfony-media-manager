<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Exception;

final class FileUploadException extends \RuntimeException implements TranslatableExceptionInterface
{
    use TranslatableExceptionTrait;

    public static function notAllowed(string $mimeType): self
    {
        return new self(\sprintf('The file type "%s" is not allowed.', $mimeType))->translated('error.mime_not_allowed', ['%mime%' => $mimeType]);
    }

    public static function tooLarge(int $size, int $maxSizeInKb): self
    {
        $sizeInKb = (int) ceil($size / 1024);

        return new self(\sprintf('The file is too large (%d KB, %d KB allowed).', $sizeInKb, $maxSizeInKb))
            ->translated('error.too_large', ['%size%' => $sizeInKb, '%max%' => $maxSizeInKb]);
    }

    /**
     * Refused by a FileUploadingEvent listener (an antivirus...).
     */
    public static function rejected(string $name, string $reason): self
    {
        return new self(\sprintf('The file "%s" was refused: %s', $name, $reason))->translated('error.rejected', ['%name%' => $name, '%reason%' => $reason]);
    }

    public static function couldNotStore(string $name, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('The file "%s" could not be stored.', $name), previous: $previous)->translated('error.could_not_store', ['%name%' => $name]);
    }
}
