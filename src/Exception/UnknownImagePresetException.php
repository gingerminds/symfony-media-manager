<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Exception;

final class UnknownImagePresetException extends \InvalidArgumentException
{
    public static function named(string $preset): self
    {
        return new self(\sprintf('The image preset "%s" is not declared in gingerminds_media_manager.images.presets.', $preset));
    }
}
