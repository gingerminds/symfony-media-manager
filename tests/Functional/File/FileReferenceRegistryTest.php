<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\File;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\MediaManagerBundle\File\Reference\FileReferenceRegistry;
use Gingerminds\MediaManagerBundle\File\Reference\FileUsage;
use Gingerminds\MediaManagerBundle\Tests\Application\Entity\Article;
use Gingerminds\MediaManagerBundle\Tests\Application\Entity\Page;
use Gingerminds\MediaManagerBundle\Tests\Functional\FileFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FileReferenceRegistryTest extends KernelTestCase
{
    private FileReferenceRegistry $registry;
    private FileFactory $files;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->registry = self::getContainer()->get('test.file_reference_registry');
        $this->files = new FileFactory(self::getContainer()->get('test.file_storage'));
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testUsagesOfSeveralFilesAtOnce(): void
    {
        $cover = $this->files->text('cover.txt', 'cover');
        $attachment = $this->files->text('attachment.txt', 'attachment');
        $block = $this->files->text('block.txt', 'block');
        $unused = $this->files->text('unused.txt', 'unused');

        $article = new Article('Tractor');
        $article->cover = $cover;
        $article->attachments->add($attachment);
        $article->attachments->add($cover);
        $page = new Page('Home', ['blocks' => [['type' => 'image', 'file' => $block->getId()]]]);
        $this->entityManager->persist($article);
        $this->entityManager->persist($page);
        $this->entityManager->flush();

        $usages = $this->registry->usages([$cover, $attachment, $block, $unused]);

        $articleUsage = new FileUsage('article.name_s', 'Tractor', '/admin/articles/' . $article->getId() . '/edit');
        self::assertEquals([$articleUsage], $usages[$cover->getId()], 'Two fields, one usage.');
        self::assertEquals([$articleUsage], $usages[$attachment->getId()]);
        self::assertEquals([new FileUsage('Page', 'Home', '/pages/' . $page->getId())], $usages[$block->getId()]);
        self::assertSame([], $usages[$unused->getId()]);

        self::assertTrue($this->registry->isUsed($block));
        self::assertFalse($this->registry->isUsed($unused));
        self::assertEqualsCanonicalizing([$cover->getId(), $attachment->getId(), $block->getId()], $this->registry->usedIds());
    }

    public function testReplacePointsEveryReferenceToTheNewFile(): void
    {
        $old = $this->files->text('old.txt', 'old');
        $new = $this->files->text('new.txt', 'new');
        $other = $this->files->text('other.txt', 'other');

        $article = new Article('Tractor');
        $article->cover = $old;
        $article->attachments->add($old);
        $article->attachments->add($new);
        $page = new Page('Home', ['file' => $old->getId(), 'files' => [$other->getId(), $old->getId()]]);
        $this->entityManager->persist($article);
        $this->entityManager->persist($page);
        $this->entityManager->flush();

        self::assertSame(3, $this->registry->replace($old, $new));
        $this->entityManager->clear();

        $article = $this->entityManager->find(Article::class, $article->getId());
        $page = $this->entityManager->find(Page::class, $page->getId());
        self::assertSame($new->getId(), $article?->cover?->getId());
        self::assertSame([$new->getId()], $article->attachments->map(static fn ($file): string => $file->getId())->getValues());
        self::assertSame(['file' => $new->getId(), 'files' => [$other->getId(), $new->getId()]], $page?->content);
        self::assertFalse($this->registry->isUsed($old));
    }
}
