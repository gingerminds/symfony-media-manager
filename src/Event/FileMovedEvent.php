<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Event;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The path of a file changed (moved, renamed, its folder moved, relocated), after the flush.
 */
final class FileMovedEvent extends Event
{
    public function __construct(
        public readonly FileInterface $file,
        public readonly string $previousPath,
    ) {
    }
}
