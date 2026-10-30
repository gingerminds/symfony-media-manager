<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Event;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A file row created (upload, index), after its flush.
 */
final class FileStoredEvent extends Event
{
    public function __construct(
        public readonly FileInterface $file,
    ) {
    }
}
