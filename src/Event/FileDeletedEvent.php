<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Event;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A file row deleted, after the flush (its physical file goes with its last row).
 */
final class FileDeletedEvent extends Event
{
    public function __construct(
        public readonly FileInterface $file,
    ) {
    }
}
