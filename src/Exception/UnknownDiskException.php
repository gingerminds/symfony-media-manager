<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Exception;

final class UnknownDiskException extends \InvalidArgumentException
{
    /**
     * @param list<string> $declared
     */
    public static function named(string $disk, array $declared): self
    {
        return new self(\sprintf(
            'The disk "%s" is not declared in gingerminds_media_manager.storage.disks (declared: "%s").',
            $disk,
            implode('", "', $declared),
        ));
    }
}
