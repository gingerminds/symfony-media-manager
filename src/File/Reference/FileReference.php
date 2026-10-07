<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File\Reference;

/**
 * A file used by an entity, in one of its fields.
 */
final readonly class FileReference
{
    public function __construct(
        public string $fileId,
        public object $owner,
        public string $field,
    ) {
    }
}
