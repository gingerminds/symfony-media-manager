<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\File;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\MediaManagerBundle\Entity\File\File;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\Media;
use Gingerminds\MediaManagerBundle\Exception\FileInUseException;
use Gingerminds\MediaManagerBundle\Exception\InvalidPathException;
use Gingerminds\MediaManagerBundle\Exception\LibraryException;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\File\LibraryQuery;
use Gingerminds\MediaManagerBundle\Image\ImageProcessor;
use Gingerminds\MediaManagerBundle\Tests\Application\Entity\Article;
use Gingerminds\MediaManagerBundle\Tests\Application\Entity\Page;
use Gingerminds\MediaManagerBundle\Tests\Functional\FileFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FileLibraryTest extends KernelTestCase
{
    private FileLibrary $library;
    private FileFactory $files;
    private FilesystemOperator $filesystem;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->library = self::getContainer()->get('test.file_library');
        $this->files = new FileFactory(self::getContainer()->get('test.file_storage'));
        $this->filesystem = self::getContainer()->get('test.storage.default');
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testDirectories(): void
    {
        self::assertSame([], $this->library->directories());

        self::assertSame('actualites-2026', $this->library->mkdir('', 'Actualités 2026'));
        self::assertSame('actualites-2026/fr', $this->library->mkdir('/actualites-2026', 'FR'));
        $this->library->mkdir('', 'brochures');
        $this->filesystem->createDirectory('library/.hidden');

        self::assertSame(['actualites-2026', 'brochures'], $this->library->directories());
        self::assertSame(['actualites-2026/fr'], $this->library->directories('actualites-2026'));

        $this->expectException(LibraryException::class);
        $this->library->mkdir('', 'brochures');
    }

    public function testTheDirectoriesStayInTheLibrary(): void
    {
        $this->expectException(InvalidPathException::class);
        $this->library->directories('../private');
    }

    public function testAMissingDirectoryIsAnError(): void
    {
        $this->expectException(LibraryException::class);
        $this->library->files(new LibraryQuery('missing'));
    }

    public function testRmdirOnlyRemovesEmptyDirectories(): void
    {
        $this->library->mkdir('', 'empty');
        $this->library->mkdir('', 'full');
        $this->files->text(directory: 'full');

        $this->library->rmdir('empty');
        self::assertSame(['full'], $this->library->directories());

        try {
            $this->library->rmdir('full');
            self::fail('A directory with files is kept.');
        } catch (LibraryException) {
        }

        $this->expectException(LibraryException::class);
        $this->library->rmdir('');
    }

    public function testFilesOfADirectory(): void
    {
        $this->library->mkdir('', 'docs');
        $this->library->mkdir('docs', 'old');
        $this->files->text('b.txt', 'bravo', 'docs');
        $this->files->text('A.txt', 'alpha', 'docs');
        $this->files->text('nested.txt', 'nested', 'docs/old');
        $this->files->text('root.txt', 'root');

        self::assertSame(['A.txt', 'b.txt'], $this->names(new LibraryQuery('docs')));
        self::assertSame(['A.txt', 'b.txt', 'nested.txt'], $this->names(new LibraryQuery('docs', recursive: true)));
        self::assertSame(['nested.txt', 'A.txt', 'b.txt'], $this->names(new LibraryQuery('docs', recursive: true, sortBy: 'date', sort: 'desc')));
        self::assertSame(['root.txt'], $this->names(new LibraryQuery()));

        $page = $this->library->files(new LibraryQuery('docs', recursive: true, page: 2, itemsPerPage: 2));
        self::assertSame(3, $page->getTotalItems());
        self::assertCount(1, $page->getItems());
    }

    public function testFilters(): void
    {
        $photo = $this->files->png('Vacances.png');
        $notes = $this->files->text('notes.txt', 'notes');
        $copy = $this->files->text('copy.txt', 'notes');
        $used = $this->files->text('used.txt', 'used');
        $article = new Article('Tractor');
        $article->cover = $used;
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        self::assertSame(['Vacances.png'], $this->names(new LibraryQuery(search: 'VACANCES')));
        self::assertSame(['Vacances.png'], $this->names(new LibraryQuery(type: 'image')));
        self::assertSame(['copy.txt', 'notes.txt', 'used.txt'], $this->names(new LibraryQuery(type: 'document')));
        self::assertSame(['copy.txt', 'notes.txt'], $this->names(new LibraryQuery(duplicates: true)));
        self::assertSame(['copy.txt', 'notes.txt', 'Vacances.png'], $this->names(new LibraryQuery(orphans: true)));
        self::assertSame([], $this->names(new LibraryQuery(createdFrom: new \DateTimeImmutable('+1 day'))));
        self::assertSame(4, $this->library->files(new LibraryQuery(createdTo: new \DateTimeImmutable('+1 day')))->getTotalItems());
        unset($photo, $notes, $copy);
    }

    public function testTheSearchIgnoresTheDirectoryNames(): void
    {
        $this->library->mkdir('', 'stress-test');
        $this->files->text('photo.png', 'photo', 'stress-test');
        $this->files->text('Test report.txt', 'report', 'stress-test');

        self::assertSame(['Test report.txt'], $this->names(new LibraryQuery(recursive: true, search: 'test')));
    }

    public function testUploadReturnsTheExistingFileForAKnownContent(): void
    {
        $this->library->mkdir('', 'docs');
        $first = $this->library->upload($this->files->upload('report.txt', 'same'), 'docs');
        $second = $this->library->upload($this->files->upload('copy of report.txt', 'same'));

        self::assertFalse($first->duplicate);
        self::assertSame('library/docs/report.txt', $first->file->getPath());
        self::assertTrue($second->duplicate);
        self::assertSame($first->file->getId(), $second->file->getId());
        self::assertSame(1, $this->entityManager->getRepository(File::class)->count([]));
        self::assertFalse($this->filesystem->fileExists('library/copy-of-report.txt'));
    }

    public function testRenameKeepsTheIdTheDirectoryAndTheExtension(): void
    {
        $this->library->mkdir('', 'docs');
        $file = $this->files->text('report.txt', 'report', 'docs');
        $this->files->text('final.txt', 'taken', 'docs');
        $id = $file->getId();

        $this->library->rename($file, 'Final.TXT');

        self::assertSame($id, $file->getId());
        self::assertSame('Final.txt', $file->getOriginalName());
        self::assertSame('library/docs/final-1.txt', $file->getPath());
        self::assertSame('report', $this->filesystem->read('library/docs/final-1.txt'));
        self::assertFalse($this->filesystem->fileExists('library/docs/report.txt'));

        $this->entityManager->clear();
        self::assertSame('library/docs/final-1.txt', $this->entityManager->find(File::class, $id)?->getPath());
    }

    public function testRenamePurgesThePresetsOfTheOldPath(): void
    {
        $photo = $this->files->png();
        $cached = self::getContainer()->get('gingerminds_media_manager.image.processor')->process($photo, 'thumbnail');
        self::assertTrue($this->filesystem->fileExists($cached));

        $this->library->rename($photo, 'Portrait');

        self::assertSame('library/portrait.png', $photo->getPath());
        self::assertFalse($this->filesystem->fileExists($cached));
    }

    public function testMove(): void
    {
        $this->library->mkdir('', 'archive');
        $this->files->text('a.txt', 'archived', 'archive');
        $a = $this->files->text('a.txt', 'alpha');
        $b = $this->files->text('b.txt', 'bravo');

        $this->library->move([$a, $b], 'archive');

        self::assertSame('library/archive/a-1.txt', $a->getPath());
        self::assertSame('library/archive/b.txt', $b->getPath());
        self::assertSame('alpha', $this->filesystem->read('library/archive/a-1.txt'));
        self::assertSame([], $this->names(new LibraryQuery()));

        $this->library->move([$b], '');
        self::assertSame('library/b.txt', $b->getPath());

        $this->expectException(LibraryException::class);
        $this->library->move([$a], 'missing');
    }

    public function testAFailedSaveMovesTheFilesBack(): void
    {
        $this->library->mkdir('', 'archive');
        $file = $this->files->text('a.txt', 'alpha');
        $this->entityManager->getConnection()->executeStatement('CREATE TRIGGER fail_files BEFORE UPDATE ON files BEGIN SELECT RAISE(ABORT, \'failure\'); END');

        try {
            $this->library->move([$file], 'archive');
            self::fail('The save fails.');
        } catch (\Throwable) {
        }

        self::assertSame('library/a.txt', $file->getPath());
        self::assertTrue($this->filesystem->fileExists('library/a.txt'));
        self::assertFalse($this->filesystem->fileExists('library/archive/a.txt'));
    }

    public function testDeleteKeepsTheUsedFiles(): void
    {
        $used = $this->files->text('used.txt', 'used');
        $block = $this->files->text('block.txt', 'block');
        $free = $this->files->text('free.txt', 'free');
        $article = new Article('Tractor');
        $article->cover = $used;
        $this->entityManager->persist($article);
        $this->entityManager->persist(new Page('Home', ['file' => $block->getId()]));
        $this->entityManager->flush();

        $result = $this->library->delete([$used, $block, $free]);

        self::assertSame([$free], $result->deleted);
        self::assertSame([$used, $block], array_column($result->blocked, 'file'));
        self::assertSame('Tractor', $result->blocked[0]['usages'][0]->title);
        self::assertSame('Home', $result->blocked[1]['usages'][0]->title);
        self::assertFalse($this->filesystem->fileExists('library/free.txt'));
        self::assertTrue($this->filesystem->fileExists('library/used.txt'));

        try {
            $this->library->deleteFile($used);
            self::fail('A used file is kept.');
        } catch (FileInUseException $exception) {
            self::assertSame($used, $exception->usedFile);
            self::assertSame('/admin/articles/' . $article->getId() . '/edit', $exception->usages[0]->editUrl);
        }
    }

    public function testTheFilesOfAMediaAreKeptAndMerged(): void
    {
        $file = $this->files->text('brochure.txt', 'brochure');
        $thumbnail = $this->files->png('cover.png');
        $copy = $this->files->png('cover-copy.png');
        $media = new Media();
        $media->setCode('media');
        $media->setName('Brochure');
        $media->setFile($file);
        $media->setThumbnail($copy);
        $this->entityManager->persist($media);
        $this->entityManager->flush();

        $result = $this->library->delete([$file, $copy]);

        self::assertSame([], $result->deleted);
        self::assertSame('Brochure', $result->blocked[0]['usages'][0]->title);
        self::assertSame('Média', $result->blocked[0]['usages'][0]->label);
        self::assertSame('/admin/medias/' . $media->getId() . '/edit', $result->blocked[1]['usages'][0]->editUrl);

        self::assertSame(1, $this->library->mergeDuplicates($thumbnail, [$copy]));
        self::assertSame($thumbnail, $media->getThumbnail());
        self::assertNull($this->entityManager->find(File::class, $copy->getId()));
    }

    public function testMoveAndRenameADirectory(): void
    {
        $this->library->mkdir('', 'docs');
        $this->library->mkdir('docs', 'empty');
        $this->library->mkdir('', 'archives');
        $notes = $this->files->text('notes.txt', 'notes', 'docs');
        $photo = $this->files->png('photo.png', 20, 20);
        $this->library->mkdir('docs', 'photos');
        $this->library->move([$photo], 'docs/photos');
        $this->filesystem->write('library/docs/raw.txt', 'not in the files table');
        $preset = self::getContainer()->get(ImageProcessor::class)->process($photo, 'thumbnail');
        $article = new Article('Tractor');
        $article->cover = $photo;
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        self::assertSame('archives/docs', $this->library->moveDirectory('docs', 'archives'));

        self::assertSame('library/archives/docs/notes.txt', $notes->getPath());
        self::assertSame('library/archives/docs/photos/photo.png', $photo->getPath());
        self::assertTrue($this->filesystem->fileExists('library/archives/docs/photos/photo.png'));
        self::assertTrue($this->filesystem->fileExists('library/archives/docs/raw.txt'), 'A file missing from the table follows.');
        self::assertTrue($this->filesystem->directoryExists('library/archives/docs/empty'));
        self::assertFalse($this->filesystem->directoryExists('library/docs'));
        self::assertFalse($this->filesystem->fileExists($preset), 'The presets of the old path are purged.');
        self::assertSame($photo, $article->cover);

        self::assertSame('archives/documents-2026', $this->library->moveDirectory('archives/docs', 'archives', 'Documents 2026'));
        $this->entityManager->clear();
        self::assertSame('library/archives/documents-2026/notes.txt', $this->entityManager->find(File::class, $notes->getId())?->getPath());
        self::assertSame(['archives/documents-2026'], $this->library->directories('archives'));
        self::assertSame('archives/documents-2026', $this->library->moveDirectory('archives/documents-2026', 'archives'), 'Same place: nothing to do.');
    }

    public function testADirectoryCannotBeMovedAnywhere(): void
    {
        $this->library->mkdir('', 'docs');
        $this->library->mkdir('docs', 'sub');
        $this->library->mkdir('', 'other');
        $this->library->mkdir('other', 'docs');

        foreach ([
            ['', 'other', 'The library root cannot be moved nor renamed.'],
            ['docs', 'docs/sub', 'The folder "docs" cannot be moved into itself or one of its subfolders.'],
            ['docs', 'docs', 'The folder "docs" cannot be moved into itself or one of its subfolders.'],
            ['docs', 'other', 'The folder "other/docs" already exists.'],
            ['missing', 'other', 'The folder "missing" does not exist.'],
            ['docs', 'missing', 'The folder "missing" does not exist.'],
        ] as [$path, $parent, $message]) {
            try {
                $this->library->moveDirectory($path, $parent);
                self::fail(\sprintf('"%s" moved to "%s".', $path, $parent));
            } catch (LibraryException $exception) {
                self::assertSame($message, $exception->trans(self::getContainer()->get('translator'), 'en'));
            }
        }

        $this->files->text('a.txt', 'content a', 'docs');
        $this->files->text('b.txt', 'content b', 'docs/sub');

        $this->expectExceptionMessage('The directory "docs" holds 2 files, more than 1.');
        $this->library->moveDirectory('docs', 'other', 'big', 1);
    }

    public function testMoveSeveralDirectories(): void
    {
        $this->library->mkdir('', 'docs');
        $this->library->mkdir('docs', 'sub');
        $this->library->mkdir('', 'photos');
        $this->library->mkdir('', 'archives');
        $this->library->mkdir('archives', 'photos');

        self::assertSame(['archives/docs'], $this->library->moveDirectories(['docs', 'docs/sub'], 'archives'), 'A subfolder follows its parent.');
        self::assertSame(['archives/docs/sub'], $this->library->directories('archives/docs'));

        $this->expectExceptionMessage('The directory "archives/photos" already exists.');
        $this->library->moveDirectories(['photos'], 'archives');
    }

    public function testDeleteSeveralDirectories(): void
    {
        $this->library->mkdir('', 'empty');
        $this->library->mkdir('', 'docs');
        $this->files->text('notes.txt', 'notes', 'docs');

        self::assertSame(['deleted' => ['empty'], 'kept' => ['docs']], $this->library->rmdirs(['empty', 'docs']));
        self::assertSame(['docs'], $this->library->directories());
    }

    public function testAFailedMoveOfADirectoryPutsEverythingBack(): void
    {
        $this->library->mkdir('', 'docs');
        $this->library->mkdir('', 'archives');
        $notes = $this->files->text('notes.txt', 'notes', 'docs');
        $failure = new class {
            public function onFlush(): never
            {
                throw new \RuntimeException('Database down.');
            }
        };
        $this->entityManager->getEventManager()->addEventListener('onFlush', $failure);

        try {
            $this->library->moveDirectory('docs', 'archives');
            self::fail('The save failed.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Database down.', $exception->getMessage());
        } finally {
            $this->entityManager->getEventManager()->removeEventListener('onFlush', $failure);
        }

        self::assertSame('library/docs/notes.txt', $notes->getPath());
        self::assertTrue($this->filesystem->fileExists('library/docs/notes.txt'));
        self::assertFalse($this->filesystem->directoryExists('library/archives/docs'));
    }

    public function testMergeDuplicates(): void
    {
        $keep = $this->files->text('logo.txt', 'logo');
        $duplicate = $this->files->text('logo-copy.txt', 'logo');
        $other = $this->files->text('other.txt', 'other');
        $article = new Article('Tractor');
        $article->cover = $duplicate;
        $page = new Page('Home', ['file' => $duplicate->getId()]);
        $this->entityManager->persist($article);
        $this->entityManager->persist($page);
        $this->entityManager->flush();

        self::assertSame(2, $this->library->mergeDuplicates($keep, [$keep, $duplicate]));

        self::assertSame($keep, $article->cover);
        self::assertSame(['file' => $keep->getId()], $page->content);
        self::assertFalse($this->filesystem->fileExists('library/logo-copy.txt'));
        self::assertSame(['logo.txt', 'other.txt'], $this->names(new LibraryQuery()));

        $this->expectException(\InvalidArgumentException::class);
        $this->library->mergeDuplicates($keep, [$other]);
    }

    public function testASharedPhysicalFileIsKeptUntilItsLastRow(): void
    {
        $file = $this->files->text('shared.txt', 'shared');
        $legacy = new File('default', $file->getPath(), 'text/plain', 'shared.txt', 6, $file->getHash());
        $this->entityManager->persist($legacy);
        $this->entityManager->flush();

        $this->library->mergeDuplicates($file, [$legacy]);

        self::assertTrue($this->filesystem->fileExists('library/shared.txt'));
    }

    /**
     * @return list<string>
     */
    private function names(LibraryQuery $query): array
    {
        return array_map(static fn (FileInterface $file): string => $file->getOriginalName(), $this->library->files($query)->getItems());
    }
}
