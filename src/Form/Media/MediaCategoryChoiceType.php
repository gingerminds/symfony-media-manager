<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Form\Media;

use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaCategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A media category picked in the tree, indented by depth; `exclude` removes a category and its descendants.
 *
 * @extends AbstractType<MediaCategoryInterface|null>
 */
final class MediaCategoryChoiceType extends AbstractType
{
    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly MediaCategoryRepository $categories,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => $this->resources->getEntityClass('media_category'),
            'exclude' => null,
            'required' => false,
            'choice_translation_domain' => false,
            'choice_label' => static fn (MediaCategoryInterface $choice): string => str_repeat('— ', self::depth($choice)) . $choice,
        ]);
        $resolver->setAllowedTypes('exclude', ['null', MediaCategoryInterface::class]);
        $resolver->setDefault('choices', fn (Options $options): array => array_values(array_filter(
            array_column($this->categories->findFlatTree(), 0),
            static fn (MediaCategoryInterface $choice): bool => !$options['exclude'] instanceof MediaCategoryInterface || !$options['exclude']->contains($choice),
        )));
    }

    public function getParent(): string
    {
        return EntityType::class;
    }

    private static function depth(MediaCategoryInterface $category): int
    {
        $depth = 0;

        for ($parent = $category->getParent(); $parent instanceof MediaCategoryInterface; $parent = $parent->getParent()) {
            ++$depth;
        }

        return $depth;
    }
}
