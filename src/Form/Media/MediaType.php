<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Form\Media;

use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;
use Gingerminds\MediaManagerBundle\Form\File\FilePickerType;
use Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle;
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
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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
            ->add('category', MediaCategoryChoiceType::class, [
                'label' => 'media.field.category',
                'placeholder' => 'media.placeholder.category',
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
