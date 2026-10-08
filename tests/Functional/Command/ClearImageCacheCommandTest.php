<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\Command;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\Media;
use Gingerminds\MediaManagerBundle\Image\ImageProcessor;
use Gingerminds\MediaManagerBundle\Tests\Functional\FileFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ClearImageCacheCommandTest extends KernelTestCase
{
    private FileFactory $files;
    private ImageProcessor $images;
    private FilesystemOperator $storage;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->files = new FileFactory(self::getContainer()->get('test.file_storage'));
        $this->images = self::getContainer()->get(ImageProcessor::class);
        $this->storage = self::getContainer()->get('test.storage.default');
    }

    public function testClearOneFile(): void
    {
        $kept = $this->files->png('kept.png');
        $cleared = $this->files->png('cleared.png');
        $keptPath = $this->images->process($kept, 'thumbnail');
        $clearedPath = $this->images->process($cleared, 'thumbnail');

        $tester = $this->command();
        $tester->execute(['--file' => [$cleared->getId(), '0199b0c4-7a7c-7c5e-9d3e-5b8f8a6c1d2e']]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('not found, skipped', $tester->getDisplay());
        self::assertTrue($this->storage->fileExists($keptPath));
        self::assertFalse($this->storage->fileExists($clearedPath));
    }

    public function testClearTheFilesOfAMedia(): void
    {
        $kept = $this->files->png('kept.png');
        $file = $this->files->png('photo.png', 20, 20);
        $thumbnail = $this->files->png('cover.png', 30, 30);
        $media = new Media();
        $media->setCode('media');
        $media->setFile($file);
        $media->setThumbnail($thumbnail);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($media);
        $entityManager->flush();
        $keptPath = $this->images->process($kept, 'thumbnail');
        $paths = [$this->images->process($file, 'thumbnail'), $this->images->process($thumbnail, 'card')];

        $tester = $this->command();
        $tester->execute(['--media' => [(string) $media->getId(), '999999']]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Media "999999" not found, skipped', $tester->getDisplay());
        self::assertTrue($this->storage->fileExists($keptPath));
        self::assertFalse($this->storage->fileExists($paths[0]));
        self::assertFalse($this->storage->fileExists($paths[1]));
    }

    public function testClearEverythingAfterConfirmation(): void
    {
        $path = $this->images->process($this->files->png(), 'thumbnail');

        $tester = $this->command();
        $tester->setInputs(['no']);
        $tester->execute([]);
        self::assertTrue($this->storage->fileExists($path));

        $tester->execute(['--force' => true]);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('disk "default"', $tester->getDisplay());
        self::assertFalse($this->storage->fileExists($path));
    }

    public function testDeletingAFileClearsItsPresets(): void
    {
        $file = $this->files->png();
        $path = $this->images->process($file, 'thumbnail');

        self::getContainer()->get('test.file_storage')->delete($file);

        self::assertFalse($this->storage->fileExists($path));
    }

    private function command(): CommandTester
    {
        return new CommandTester(new Application(self::$kernel)->find('gingerminds:media:cache:clear'));
    }
}
