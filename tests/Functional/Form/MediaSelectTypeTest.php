<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\Form;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\Media;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;
use Gingerminds\MediaManagerBundle\Form\Media\MediaSelectType;
use Gingerminds\MediaManagerBundle\Tests\Application\Entity\Article;
use Gingerminds\MediaManagerBundle\Tests\Application\Entity\ArticleMedia;
use Gingerminds\MediaManagerBundle\Tests\Functional\FileFactory;
use Gingerminds\MediaManagerBundle\Tests\Functional\Fixtures;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

final class MediaSelectTypeTest extends KernelTestCase
{
    private Fixtures $fixtures;
    private FileFactory $files;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->fixtures = new Fixtures($this->entityManager, self::getContainer()->get(UserPasswordHasherInterface::class));
        $this->files = new FileFactory(self::getContainer()->get('test.file_storage'));
        self::getContainer()->get('translator')->setLocale('en');
    }

    public function testOneOrSeveralMediasOrTheirIds(): void
    {
        [$first, $second] = $this->medias(2);
        $article = new Article();

        $form = $this->form($article, ['media' => [], 'ids' => ['multiple' => true, 'as_id' => true, 'mapped' => false]]);
        $form->submit(['media' => (string) $second->getId(), 'ids' => $second->getId() . ',' . $first->getId()]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($second, $article->media);
        self::assertSame([$second->getId(), $first->getId()], $form->get('ids')->getData());

        $form = $this->form(new Article(), ['media' => []]);
        $form->submit(['media' => $first->getId() . ',' . $second->getId()]);
        self::assertFalse($form->isValid(), 'One media only.');

        $form = $this->form(new Article(), ['media' => []]);
        $form->submit(['media' => '999999']);
        self::assertSame('This media no longer exists.', $form->get('media')->getErrors()->current()->getMessage());
    }

    public function testTheAllowedCategoriesIncludeTheirSubcategories(): void
    {
        $photos = $this->fixtures->mediaCategory('photos');
        $landscapes = $this->fixtures->mediaCategory('landscapes', $photos);
        $videos = $this->fixtures->mediaCategory('videos');
        $landscape = $this->fixtures->media($this->files->png('landscape.png'), 'Landscape', $landscapes);
        $clip = $this->fixtures->media($this->files->text('clip.txt', 'clip'), 'Clip', $videos);

        $form = $this->form(new Article(), ['media' => ['categories' => ['photos']]]);
        $form->submit(['media' => (string) $landscape->getId()]);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        $form = $this->form(new Article(), ['media' => ['categories' => ['photos']]]);
        $form->submit(['media' => (string) $clip->getId()]);
        self::assertSame('The media "Clip" is not in an allowed category.', $form->get('media')->getErrors()->current()->getMessage());

        self::assertSame([$photos->getId()], $form->createView()['media']->vars['categories']);
    }

    public function testCollectionsOfMediaLinks(): void
    {
        [$first, $second, $third] = $this->medias(3);
        $article = new Article('Tractor');
        $this->entityManager->persist($article);

        $form = $this->linksForm($article);
        $form->submit(['visuals' => $second->getId() . ',' . $first->getId(), 'documents' => (string) $third->getId()]);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->entityManager->flush();

        self::assertSame([[$second, 'visual', 0], [$first, 'visual', 1], [$third, 'document', 0]], $this->links($article));
        $view = $this->linksForm($article)->createView();
        self::assertSame($second->getId() . ',' . $first->getId(), $view['visuals']->vars['value']);
        self::assertSame([$second->getId(), $first->getId()], array_column($view['visuals']->vars['medias'], 'id'));

        $form = $this->linksForm($article);
        $form->submit(['visuals' => $first->getId() . ',' . $third->getId(), 'documents' => (string) $third->getId()]);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $article = $this->entityManager->find(Article::class, $article->getId());
        self::assertInstanceOf(Article::class, $article);
        self::assertSame(
            [[$first->getId(), 'visual', 0], [$third->getId(), 'visual', 1], [$third->getId(), 'document', 0]],
            array_map(static fn (array $link): array => [$link[0]->getId(), $link[1], $link[2]], $this->links($article)),
            'The removed link is deleted, the other collection is untouched.',
        );
        self::assertSame(3, $this->entityManager->getRepository(ArticleMedia::class)->count());
    }

    public function testACollectionNeedsALinkFactory(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->form(new Article(), ['mediaLinks' => ['multiple' => true, 'collection' => 'visual']]);
    }

    public function testTheWidget(): void
    {
        [$media] = $this->medias(1);
        $article = new Article();
        $article->media = $media;

        $view = $this->form($article, ['media' => ['per_page' => 12]])->createView();
        $vars = $view['media']->vars;

        self::assertSame([$media->getId()], array_column($vars['medias'], 'id'));
        self::assertSame('/api/files/' . $media->getFile()?->getId() . '/thumbnail', $vars['medias'][0]['thumbnailUrl']);
        self::assertSame('/admin/medias/picker', $vars['picker_url']);
        self::assertSame(12, $vars['per_page']);
        self::assertFalse($vars['can_pick'], 'No user: no access to the medias.');

        $html = self::getContainer()->get(Environment::class)->createTemplate('{{ form_widget(form.media) }}')->render(['form' => $view]);
        self::assertStringContainsString('data-controller="gm-media-select"', $html);
        self::assertStringContainsString('name="form[media]" value="' . $media->getId() . '"', $html);
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     *
     * @return FormInterface<mixed>
     */
    private function form(Article $article, array $fields): FormInterface
    {
        $builder = self::getContainer()->get(FormFactoryInterface::class)
            ->createBuilder(FormType::class, $article, ['data_class' => Article::class, 'csrf_protection' => false]);

        foreach ($fields as $name => $options) {
            $builder->add($name, MediaSelectType::class, ['required' => false, ...$options]);
        }

        return $builder->getForm();
    }

    /**
     * @return FormInterface<mixed>
     */
    private function linksForm(Article $article): FormInterface
    {
        $options = static fn (string $collection): array => [
            'multiple' => true,
            'property_path' => 'mediaLinks',
            'collection' => $collection,
            'link_factory' => static fn (MediaInterface $media, string $collection): ArticleMedia => new ArticleMedia($article, $media, $collection),
        ];

        return $this->form($article, ['visuals' => $options('visual'), 'documents' => $options('document')]);
    }

    /**
     * @return list<array{MediaInterface, string, int}>
     */
    private function links(Article $article): array
    {
        $links = $article->mediaLinks->toArray();
        // "visual" first, then by position.
        usort($links, static fn (ArticleMedia $a, ArticleMedia $b): int => ($b->getCollection() <=> $a->getCollection()) ?: $a->getPosition() <=> $b->getPosition());

        return array_map(static fn (ArticleMedia $link): array => [$link->getMedia(), $link->getCollection(), $link->getPosition()], $links);
    }

    /**
     * @return list<Media>
     */
    private function medias(int $count): array
    {
        return array_map(fn (int $index): Media => $this->fixtures->media($this->files->png('media-' . $index . '.png', 10 + $index, 10)), range(1, $count));
    }
}
