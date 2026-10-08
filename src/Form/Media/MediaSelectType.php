<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Form\Media;

use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Gingerminds\MediaManagerBundle\Media\MediaPresenter;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaCategoryRepository;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Medias picked in the media picker modal: one (MediaInterface), several (`multiple`, a collection),
 * their ids (`as_id`), or one collection of the media links of the entity (`collection`).
 *
 * @extends AbstractType<mixed>
 */
final class MediaSelectType extends AbstractType
{
    public function __construct(
        private readonly MediaRepository $medias,
        private readonly MediaCategoryRepository $categories,
        private readonly MediaPresenter $presenter,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addViewTransformer(new MediaSelectTransformer($this->medias, $options['multiple'], $options['as_id'], $this->allowedCategoryIds($options['categories'])));

        if (null !== $options['collection']) {
            $builder->addModelTransformer(new MediaCollectionTransformer($options['collection'], $options['link_factory'](...)));
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $ids = MediaSelectTransformer::ids(\is_string($form->getViewData()) ? $form->getViewData() : '');
        $medias = [];

        foreach ([] === $ids ? [] : $this->medias->findBy(['id' => $ids]) as $media) {
            $medias[(int) $media->getId()] = $this->presenter->media($media);
        }

        $categories = $this->categories->findByCodes($options['categories']);

        $view->vars['medias'] = array_values(array_filter(array_map(static fn (int $id): ?array => $medias[$id] ?? null, $ids)));
        $view->vars['multiple'] = $options['multiple'];
        $view->vars['categories'] = array_map(static fn (MediaCategoryInterface $category): int => (int) $category->getId(), $categories);
        $view->vars['per_page'] = $options['per_page'];
        $view->vars['can_pick'] = $this->authorizationChecker->isGranted(AbstractResourceVoter::VIEW, 'media');
        $view->vars['picker_url'] = $this->urlGenerator->generate('gingerminds_media_manager_media_picker');
        $view->vars['search_url'] = $this->urlGenerator->generate('gingerminds_media_manager_media_search');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => false,
            'multiple' => false,
            'as_id' => false,
            'categories' => [],
            'per_page' => 24,
            'collection' => null,
            'link_factory' => null,
            'empty_data' => '',
            'invalid_message' => 'gingerminds_media_manager.media_select.invalid',
        ]);
        $resolver->setAllowedTypes('multiple', 'bool');
        $resolver->setAllowedTypes('as_id', 'bool');
        $resolver->setAllowedTypes('categories', 'string[]');
        $resolver->setAllowedTypes('per_page', 'int');
        $resolver->setAllowedTypes('collection', ['null', 'string', \BackedEnum::class]);
        $resolver->setAllowedTypes('link_factory', ['null', 'callable']);
        $resolver->setInfo('categories', 'Codes of the allowed categories, their subcategories included; one code locks the category filter of the modal.');
        $resolver->setInfo('collection', 'Collection of the media links of the entity (property_path: the links); needs `multiple` and `link_factory`.');
        $resolver->setNormalizer('link_factory', static function (Options $options, ?callable $factory): ?callable {
            if (null !== $options['collection'] && (null === $factory || !$options['multiple'] || $options['as_id'])) {
                throw new InvalidConfigurationException('The "collection" option needs "multiple", a "link_factory" and no "as_id".');
            }

            return $factory;
        });
    }

    public function getBlockPrefix(): string
    {
        return 'gm_media_select';
    }

    /**
     * @param list<string> $codes
     *
     * @return list<int>|null
     */
    private function allowedCategoryIds(array $codes): ?array
    {
        if ([] === $codes) {
            return null;
        }

        $ids = array_map(static fn (MediaCategoryInterface $category): int => (int) $category->getId(), $this->categories->findByCodes($codes));

        return $this->categories->findIdsWithDescendants($ids);
    }
}
