<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\Form;

use Gingerminds\MediaManagerBundle\Form\File\FilePickerType;
use Gingerminds\MediaManagerBundle\Tests\Application\Entity\Article;
use Gingerminds\MediaManagerBundle\Tests\Functional\FileFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Twig\Environment;

final class FilePickerTypeTest extends KernelTestCase
{
    private FileFactory $files;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->files = new FileFactory(self::getContainer()->get('test.file_storage'));
        self::getContainer()->get('translator')->setLocale('en');
    }

    public function testAnEntityGetsThePickedFiles(): void
    {
        $photo = $this->files->png();
        $first = $this->files->text('first.txt', 'first');
        $second = $this->files->text('second.txt', 'second');
        $article = new Article();

        $form = $this->articleForm($article);
        $form->submit(['title' => 'Tractor', 'cover' => $photo->getId(), 'attachments' => $second->getId() . ',' . $first->getId()]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($photo, $article->cover);
        self::assertSame([$second, $first], $article->attachments->toArray());
    }

    public function testTheAcceptedTypesAreChecked(): void
    {
        $notes = $this->files->text('notes.txt', 'notes');

        $form = $this->articleForm(new Article());
        $form->submit(['title' => 'Tractor', 'cover' => $notes->getId(), 'attachments' => '']);

        self::assertFalse($form->isValid());
        self::assertSame('The file "notes.txt" is not of an accepted type (image/*).', $form->get('cover')->getErrors()->current()->getMessage());
    }

    public function testAnUnknownFileIsRefused(): void
    {
        $form = $this->articleForm(new Article());
        $form->submit(['title' => 'Tractor', 'cover' => '0190a8b2-3c4d-7e5f-8a9b-0c1d2e3f4a5b', 'attachments' => '']);

        self::assertSame('This file is no longer in the library.', $form->get('cover')->getErrors()->current()->getMessage());
    }

    public function testASingleFieldTakesOneFile(): void
    {
        $form = $this->articleForm(new Article());
        $form->submit(['title' => 'Tractor', 'cover' => $this->files->png('a.png')->getId() . ',' . $this->files->png('b.png', 10)->getId(), 'attachments' => '']);

        self::assertSame('This file is not valid.', $form->get('cover')->getErrors()->current()->getMessage());
    }

    public function testIdsForAJsonField(): void
    {
        $first = $this->files->text('first.txt', 'first');
        $second = $this->files->text('second.txt', 'second');

        $form = $this->factory()->createBuilder(FormType::class, null, ['csrf_protection' => false])
            ->add('file', FilePickerType::class, ['as_id' => true])
            ->add('files', FilePickerType::class, ['as_id' => true, 'multiple' => true])
            ->getForm();
        $form->submit(['file' => $first->getId(), 'files' => $second->getId() . ',' . $first->getId()]);

        self::assertSame(['file' => $first->getId(), 'files' => [$second->getId(), $first->getId()]], $form->getData());

        $form = $this->factory()->create(FilePickerType::class, null, ['as_id' => true, 'csrf_protection' => false]);
        $form->submit('');
        self::assertNull($form->getData());
    }

    public function testTheWidget(): void
    {
        $photo = $this->files->png();
        $article = new Article('Tractor');
        $article->cover = $photo;

        $view = $this->articleForm($article)->createView();
        $cover = $view['cover']->vars;

        self::assertSame($photo->getId(), $cover['value']);
        self::assertSame([$photo->getId()], array_column($cover['files'], 'id'));
        self::assertSame(['image/*'], $cover['accept']);
        self::assertSame(['image'], $cover['types'], 'Only the image type in the filter of the modal.');
        self::assertSame('/admin/files/picker', $cover['picker_url']);
        self::assertStringEndsWith('/card', (string) $cover['preview_url']);
        self::assertFalse($cover['can_pick'], 'No user: no access to the library.');
        self::assertTrue($view['attachments']->vars['multiple']);

        $html = self::getContainer()->get(Environment::class)
            ->createTemplate('{{ form_widget(form.cover) }}')
            ->render(['form' => $view]);

        self::assertStringContainsString('data-controller="gm-file-picker"', $html);
        self::assertStringContainsString('name="form[cover]" value="' . $photo->getId() . '"', $html);
        self::assertStringContainsString('Accepted types: image/*', $html);
        self::assertStringContainsString('disabled', $html);
    }

    /**
     * @return FormInterface<Article>
     */
    private function articleForm(Article $article): FormInterface
    {
        return $this->factory()->createBuilder(FormType::class, $article, ['data_class' => Article::class, 'csrf_protection' => false])
            ->add('title', TextType::class)
            ->add('cover', FilePickerType::class, ['accept' => ['image/*'], 'preview_preset' => 'card', 'required' => false])
            ->add('attachments', FilePickerType::class, ['multiple' => true, 'required' => false])
            ->getForm();
    }

    private function factory(): FormFactoryInterface
    {
        return self::getContainer()->get(FormFactoryInterface::class);
    }
}
