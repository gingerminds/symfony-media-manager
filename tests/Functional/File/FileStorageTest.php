<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\File;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\MediaManagerBundle\Entity\File\File;
use Gingerminds\MediaManagerBundle\Exception\FileUploadException;
use Gingerminds\MediaManagerBundle\Exception\InvalidPathException;
use Gingerminds\MediaManagerBundle\Exception\UnknownDiskException;
use Gingerminds\MediaManagerBundle\File\FileStorage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileStorageTest extends KernelTestCase
{
    private FileStorage $storage;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->storage = self::getContainer()->get('test.file_storage');
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testStoreWritesTheFileAndItsRow(): void
    {
        $file = $this->storage->store($this->upload('Mon Fichier été.txt', 'hello'));

        self::assertSame('default', $file->getDisk());
        self::assertSame('library/mon-fichier-ete.txt', $file->getPath());
        self::assertSame('text/plain', $file->getMimeType());
        self::assertSame('Mon Fichier été.txt', $file->getOriginalName());
        self::assertSame(5, $file->getSize());
        self::assertSame(hash('sha256', 'hello'), $file->getHash());
        self::assertSame('hello', stream_get_contents($this->storage->readStream($file)));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-/', $file->getId());

        $this->entityManager->clear();
        $row = $this->entityManager->find(File::class, $file->getId());
        self::assertInstanceOf(File::class, $row);
        self::assertNotNull($row->getCreatedAt());
    }

    public function testATakenNameGetsASuffix(): void
    {
        $paths = array_map(
            fn (): string => $this->storage->store($this->upload('notes.txt', 'content'), 'fr/news')->getPath(),
            range(1, 3),
        );

        self::assertSame(['library/fr/news/notes.txt', 'library/fr/news/notes-1.txt', 'library/fr/news/notes-2.txt'], $paths);
    }

    public function testANotAllowedTypeIsRejected(): void
    {
        $this->expectException(FileUploadException::class);
        $this->expectExceptionMessage('application/pdf');

        $this->storage->store($this->upload('document.txt', "%PDF-1.4\n%âãÏÓ\n"));
    }

    public function testATooLargeFileIsRejected(): void
    {
        $this->expectException(FileUploadException::class);

        $this->storage->store($this->upload('big.txt', str_repeat('a', 101 * 1024)));
    }

    public function testAnUnknownDiskIsRejected(): void
    {
        $this->expectException(UnknownDiskException::class);

        $this->storage->store($this->upload('notes.txt', 'content'), disk: 'public');
    }

    public function testADirectoryOutsideTheLibraryIsRejected(): void
    {
        $this->expectException(InvalidPathException::class);

        $this->storage->store($this->upload('notes.txt', 'content'), '../outside');
    }

    public function testDeleteRemovesTheFileAndItsRow(): void
    {
        $file = $this->storage->store($this->upload('notes.txt', 'content'));
        $id = $file->getId();

        $this->storage->delete($file);

        self::assertNull($this->entityManager->find(File::class, $id));
        self::assertSame('library/notes.txt', $this->storage->store($this->upload('notes.txt', 'content'))->getPath());
    }

    private function upload(string $originalName, string $content): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'gm-upload');
        file_put_contents($path, $content);

        return new UploadedFile($path, $originalName, test: true);
    }
}
