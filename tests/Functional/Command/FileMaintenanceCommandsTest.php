<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\Command;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\MediaManagerBundle\Entity\File\File;
use Gingerminds\MediaManagerBundle\Tests\Application\Entity\Article;
use Gingerminds\MediaManagerBundle\Tests\Functional\FileFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class FileMaintenanceCommandsTest extends KernelTestCase
{
    private FileFactory $files;
    private FilesystemOperator $filesystem;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->files = new FileFactory(self::getContainer()->get('test.file_storage'));
        $this->filesystem = self::getContainer()->get('test.storage.default');
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testHash(): void
    {
        $notes = $this->files->text('notes.txt', 'notes');
        $notes->setHash(null);
        $this->row('library/missing.txt');

        $tester = $this->command('files:hash');

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('1 file(s) missing on their disk', $tester->getDisplay());
        self::assertStringContainsString('default:library/missing.txt', $tester->getDisplay());
        self::assertSame(hash('sha256', 'notes'), $this->find($notes->getId())?->getHash());

        $this->find($notes->getId())?->setHash('wrong');
        $this->entityManager->flush();
        $this->command('files:hash', ['--force' => true]);
        self::assertSame(hash('sha256', 'notes'), $this->find($notes->getId())?->getHash());
    }

    public function testIndex(): void
    {
        $this->files->text('known.txt', 'known');
        $this->filesystem->write('library/raw.txt', 'copied by hand');
        $this->filesystem->write('library/docs/Rapport final.txt', 'report');
        $this->filesystem->write('library/.hidden/secret.txt', 'hidden');

        $tester = $this->command('files:index', ['--dry-run' => true]);
        self::assertStringContainsString('2 file(s) to index (dry run)', $tester->getDisplay());
        self::assertNull($this->findByPath('library/raw.txt'));

        $this->command('files:index', ['--path' => 'docs'])->assertCommandIsSuccessful();
        self::assertNull($this->findByPath('library/raw.txt'), 'Outside --path.');
        $report = $this->findByPath('library/docs/Rapport final.txt');
        self::assertSame(['Rapport final.txt', 'text/plain', 6, hash('sha256', 'report')], [$report?->getOriginalName(), $report?->getMimeType(), $report?->getSize(), $report?->getHash()]);

        $tester = $this->command('files:index');
        self::assertStringContainsString('1 file(s) indexed', $tester->getDisplay());
        self::assertNotNull($this->findByPath('library/raw.txt'));
        self::assertNull($this->findByPath('library/.hidden/secret.txt'));
    }

    public function testDeduplicate(): void
    {
        $old = $this->files->text('logo.txt', 'logo');
        $old->setCreatedAt(new \DateTimeImmutable('-1 year'));
        $copy = $this->files->text('logo-copy.txt', 'logo');
        $article = new Article('Tractor');
        $article->cover = $copy;
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $tester = $this->command('files:deduplicate', ['--dry-run' => true]);
        self::assertStringContainsString('1 duplicate(s) to merge (dry run)', $tester->getDisplay());
        self::assertNotNull($this->find($copy->getId()));

        $tester = $this->command('files:deduplicate');
        self::assertStringContainsString('default:library/logo.txt', $tester->getDisplay());
        self::assertStringContainsString('1 duplicate(s) merged', $tester->getDisplay());
        self::assertNull($this->find($copy->getId()));
        self::assertSame($old->getId(), $this->entityManager->find(Article::class, $article->getId())?->cover?->getId());
        self::assertFalse($this->filesystem->fileExists('library/logo-copy.txt'));
    }

    public function testRelocate(): void
    {
        $this->files->text('logo.txt', 'logo');
        $this->filesystem->write('uploads/2024/logo.txt', 'old logo');
        $logo = $this->row('uploads/2024/logo.txt');
        $this->filesystem->write('uploads/shared.txt', 'shared');
        $first = $this->row('uploads/shared.txt');
        $second = $this->row('uploads/shared.txt');
        $this->row('uploads/missing.txt');

        $tester = $this->command('files:relocate', ['--dry-run' => true]);
        self::assertStringContainsString('3 file(s) to move to the library root (dry run)', $tester->getDisplay());
        self::assertTrue($this->filesystem->fileExists('uploads/2024/logo.txt'));

        $tester = $this->command('files:relocate');
        self::assertStringContainsString('2 file(s) moved', $tester->getDisplay());
        self::assertStringContainsString('default:uploads/missing.txt', $tester->getDisplay());
        self::assertSame('library/logo-1.txt', $this->find($logo->getId())?->getPath(), 'The name is taken.');
        self::assertSame('old logo', $this->filesystem->read('library/logo-1.txt'));
        self::assertSame(['library/shared.txt', 'library/shared.txt'], [$this->find($first->getId())?->getPath(), $this->find($second->getId())?->getPath()]);
        self::assertFalse($this->filesystem->fileExists('uploads/shared.txt'));
    }

    public function testOrphans(): void
    {
        $used = $this->files->text('used.txt', 'used');
        $recent = $this->files->text('recent.txt', 'recent');
        $old = $this->files->text('old.txt', 'old');
        $old->setCreatedAt(new \DateTimeImmutable('-40 days'));
        $article = new Article('Tractor');
        $article->cover = $used;
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $tester = $this->command('files:orphans');
        self::assertStringContainsString('2 unused file(s)', $tester->getDisplay());
        self::assertStringNotContainsString('used.txt ', $tester->getDisplay());

        self::assertSame(Command::INVALID, $this->command('files:orphans', ['--older-than' => 'month'])->getStatusCode());

        $tester = $this->command('files:orphans', ['--older-than' => '30', '--delete' => true]);
        self::assertStringContainsString('1 file(s) deleted, 0 kept', $tester->getDisplay());
        self::assertNull($this->find($old->getId()));
        self::assertNotNull($this->find($recent->getId()));
        self::assertNotNull($this->find($used->getId()));
    }

    /**
     * A row as imported from Laravel.
     */
    private function row(string $path): File
    {
        $file = new File('default', $path, 'text/plain', basename($path), 3);
        $this->entityManager->persist($file);
        $this->entityManager->flush();

        return $file;
    }

    private function find(string $id): ?File
    {
        $this->entityManager->clear();

        return $this->entityManager->find(File::class, $id);
    }

    private function findByPath(string $path): ?File
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(File::class)->findOneBy(['path' => $path]);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function command(string $command, array $input = []): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('gingerminds:media:' . $command));
        $tester->execute($input);

        return $tester;
    }
}
