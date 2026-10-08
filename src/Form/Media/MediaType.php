<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Form\Media;

use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;
use Gingerminds\MediaManagerBundle\Form\File\FilePickerType;
use Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaCategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<MediaInterface>
 */
class MediaType extends AbstractType
{
    public function __construct(
        protected readonly ResourceRegistry $resources,
        protected readonly MediaCategoryRepository $categories,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $depths = [];

        foreach ($this->categories->findFlatTree() as [$category, $depth]) {
            $depths[spl_object_id($category)] = [$category, $depth];
        }

        $builder
            ->add('code', TextType::class, [
                'label' => 'media.field.code',
                'empty_data' => '',
                'size' => 'md',
            ])
            ->add('name', TextType::class, [
                'label' => 'media.field.name',
                'help' => 'media.help.name',
                'required' => false,
                'size' => 'md',
            ])
            ->add('file', FilePickerType::class, [
                'label' => 'media.field.file',
                'size' => 'xl',
            ])
            ->add('thumbnail', FilePickerType::class, [
                'label' => 'media.field.thumbnail',
                'help' => 'media.help.thumbnail',
                'accept' => ['image/*'],
                'required' => false,
                'size' => 'xl',
            ])
            ->add('category', EntityType::class, [
                'label' => 'media.field.category',
                'class' => $this->resources->getEntityClass('media_category'),
                'choices' => array_column($depths, 0),
                'choice_label' => static fn (MediaCategoryInterface $choice): string => str_repeat('— ', $depths[spl_object_id($choice)][1] ?? 0) . $choice,
                'placeholder' => 'media.placeholder.category',
                'required' => false,
                'size' => 'xl',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->resources->getEntityClass('media'),
            'translation_domain' => GingermindsMediaManagerBundle::TRANSLATION_DOMAIN,
        ]);
    }
}
