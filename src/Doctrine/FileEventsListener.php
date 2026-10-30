<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Doctrine;

use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Event\FileDeletedEvent;
use Gingerminds\MediaManagerBundle\Event\FileMovedEvent;
use Gingerminds\MediaManagerBundle\Event\FileStoredEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The file events of every write of the rows (library, maintenance commands, project code),
 * dispatched once the flush is done: a listener may flush in turn.
 */
final class FileEventsListener
{
    /**
     * @var list<Event>
     */
    private array $pending = [];

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        if ($args->getObject() instanceof FileInterface) {
            $this->pending[] = new FileStoredEvent($args->getObject());
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $file = $args->getObject();

        if ($file instanceof FileInterface && $args->hasChangedField('path') && \is_string($previous = $args->getOldValue('path'))) {
            $this->pending[] = new FileMovedEvent($file, $previous);
        }
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        if ($args->getObject() instanceof FileInterface) {
            $this->pending[] = new FileDeletedEvent($args->getObject());
        }
    }

    public function postFlush(): void
    {
        $events = $this->pending;
        $this->pending = [];
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}
