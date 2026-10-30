<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\File;

use Gingerminds\MediaManagerBundle\Event\FileDeletedEvent;
use Gingerminds\MediaManagerBundle\Event\FileMovedEvent;
use Gingerminds\MediaManagerBundle\Event\FileStoredEvent;
use Gingerminds\MediaManagerBundle\Event\FileUploadingEvent;
use Gingerminds\MediaManagerBundle\Exception\FileUploadException;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\Tests\Functional\FileFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class FileEventsTest extends KernelTestCase
{
    private FileLibrary $library;
    private FileFactory $files;
    private EventDispatcherInterface $dispatcher;

    /**
     * @var list<string>
     */
    private array $dispatched = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->library = self::getContainer()->get('test.file_library');
        $this->files = new FileFactory(self::getContainer()->get('test.file_storage'));
        $this->dispatcher = self::getContainer()->get('event_dispatcher');

        $this->dispatcher->addListener(FileStoredEvent::class, fn (FileStoredEvent $event): string => $this->dispatched[] = 'stored ' . $event->file->getPath());
        $this->dispatcher->addListener(FileMovedEvent::class, fn (FileMovedEvent $event): string => $this->dispatched[] = 'moved ' . $event->previousPath . ' ' . $event->file->getPath());
        $this->dispatcher->addListener(FileDeletedEvent::class, fn (FileDeletedEvent $event): string => $this->dispatched[] = 'deleted ' . $event->file->getPath());
    }

    public function testTheWritesOfTheRowsAreDispatched(): void
    {
        $this->library->mkdir('', 'docs');
        $file = $this->library->upload($this->files->upload('notes.txt', 'notes'))->file;
        $this->library->move([$file], 'docs');
        $this->library->moveDirectory('docs', '', 'archives');
        $this->library->delete([$file]);

        self::assertSame([
            'stored library/notes.txt',
            'moved library/notes.txt library/docs/notes.txt',
            'moved library/docs/notes.txt library/archives/notes.txt',
            'deleted library/archives/notes.txt',
        ], $this->dispatched);
    }

    public function testAnUploadCanBeRejected(): void
    {
        $this->dispatcher->addListener(FileUploadingEvent::class, static function (FileUploadingEvent $event): never {
            throw FileUploadException::rejected($event->file->getFilename(), 'infected');
        });

        $this->expectException(FileUploadException::class);
        $this->expectExceptionMessage('was refused: infected');

        try {
            $this->library->upload($this->files->upload('virus.txt', 'virus'));
        } finally {
            self::assertSame([], $this->dispatched, 'Nothing stored.');
        }
    }
}
