<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\File\Input;

/**
 * A new folder (`parent`, `name`) or a renamed one (`path`, `name`).
 */
final readonly class DirectoryInput
{
    public function __construct(
        public string $parent = '',
        public string $path = '',
        public string $name = '',
    ) {
    }
}
