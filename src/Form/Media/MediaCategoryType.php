<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Form\Media;

use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<MediaCategoryInterface>
 */
class MediaCategoryType extends AbstractType
{
    public function __construct(
        protected readonly ResourceRegistry $resources,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $category = $builder->getData();

        $builder
            ->add('code', TextType::class, [
                'label' => 'media_category.field.code',
                'size' => 'md',
            ])
            ->add('name', TextType::class, [
                'label' => 'media_category.field.name',
                'size' => 'md',
            ])
            ->add('parent', MediaCategoryChoiceType::class, [
                'label' => 'media_category.field.parent',
                'exclude' => $category instanceof MediaCategoryInterface ? $category : null,
                'placeholder' => 'media_category.placeholder.parent',
                'size' => 'xl',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->resources->getEntityClass('media_category'),
            'translation_domain' => GingermindsMediaManagerBundle::TRANSLATION_DOMAIN,
        ]);
    }
}
