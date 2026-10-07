<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File\Reference;

/**
 * A reference resolved to the resource editing it: "Product « Tractor »".
 */
final readonly class FileUsage
{
    public function __construct(
        public string $label,
        public string $title,
        public ?string $editUrl = null,
    ) {
    }
}
