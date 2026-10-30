<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\File\Input;

/**
 * The fields of a file, null when not sent.
 */
final readonly class FileInput
{
    public function __construct(
        public ?string $name = null,
        public ?string $alt = null,
    ) {
    }
}
