<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Form\Media;

use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaCategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
        protected readonly MediaCategoryRepository $categories,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $category = $builder->getData();
        $depths = [];

        foreach ($this->flatten($this->categories->findTree()) as [$choice, $depth]) {
            if (!$category instanceof MediaCategoryInterface || !$category->contains($choice)) {
                $depths[spl_object_id($choice)] = [$choice, $depth];
            }
        }

        $builder
            ->add('code', TextType::class, [
                'label' => 'media_category.field.code',
                'size' => 'md',
            ])
            ->add('name', TextType::class, [
                'label' => 'media_category.field.name',
                'size' => 'md',
            ])
            ->add('parent', EntityType::class, [
                'label' => 'media_category.field.parent',
                'class' => $this->resources->getEntityClass('media_category'),
                'choices' => array_column($depths, 0),
                'choice_label' => static fn (MediaCategoryInterface $choice): string => str_repeat('— ', $depths[spl_object_id($choice)][1] ?? 0) . $choice,
                'placeholder' => 'media_category.placeholder.parent',
                'required' => false,
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

    /**
     * @param list<MediaCategoryInterface> $categories
     *
     * @return iterable<array{MediaCategoryInterface, int}> depth-first, with the depth
     */
    private function flatten(array $categories, int $depth = 0): iterable
    {
        foreach ($categories as $category) {
            yield [$category, $depth];
            yield from $this->flatten($category->getChildren(), $depth + 1);
        }
    }
}
