<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Form\File;

use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;
use Gingerminds\MediaManagerBundle\Controller\File\FileLibraryController;
use Gingerminds\MediaManagerBundle\File\FileLibraryPresenter;
use Gingerminds\MediaManagerBundle\File\LibraryStartPathProviderInterface;
use Gingerminds\MediaManagerBundle\File\MimeTypePatterns;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Gingerminds\MediaManagerBundle\Twig\MediaManagerExtension;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * One or several files picked from the library (no direct upload): the entity, a collection, or
 * their ids with `as_id` (JSON fields).
 *
 * @extends AbstractType<mixed>
 */
final class FilePickerType extends AbstractType
{
    /**
     * @param array<string, mixed> $presets
     */
    public function __construct(
        private readonly FileRepository $files,
        private readonly FileLibraryPresenter $presenter,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly LibraryStartPathProviderInterface $startPathProvider,
        private readonly array $presets,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new FilePickerTransformer($this->files, $options['multiple'], $options['as_id'], $options['accept']));
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // From the view data: also right after an invalid submission.
        $ids = FilePickerTransformer::ids(\is_string($form->getViewData()) ? $form->getViewData() : '');
        $files = [];

        foreach ([] === $ids ? [] : $this->files->findBy(['id' => array_values(array_filter($ids, Uuid::isValid(...)))]) as $file) {
            $files[$file->getId()] = $this->presenter->file($file);
        }

        $view->vars['files'] = array_values(array_filter(array_map(static fn (string $id): ?array => $files[$id] ?? null, $ids)));
        $view->vars['multiple'] = $options['multiple'];
        $view->vars['accept'] = $options['accept'];
        $view->vars['start_path'] = $options['start_path'] ?? $this->startPathProvider->getStartPath();
        $view->vars['can_pick'] = $this->authorizationChecker->isGranted(AbstractResourceVoter::VIEW, 'file');
        $view->vars['picker_url'] = $this->urlGenerator->generate('gingerminds_media_manager_file_picker');
        $view->vars['preview_url'] = isset($this->presets[$options['preview_preset']])
            ? $this->urlGenerator->generate(MediaManagerExtension::FILE_PRESET_ROUTE, ['id' => FileLibraryController::ID_PLACEHOLDER, 'preset' => $options['preview_preset']])
            : null;
        $view->vars['id_placeholder'] = FileLibraryController::ID_PLACEHOLDER;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => false,
            'multiple' => false,
            'as_id' => false,
            'accept' => [],
            'preview_preset' => FileLibraryPresenter::THUMBNAIL_PRESET,
            'start_path' => null,
            'empty_data' => '',
            'invalid_message' => 'gingerminds_media_manager.file_picker.invalid',
        ]);
        $resolver->setAllowedTypes('multiple', 'bool');
        $resolver->setAllowedTypes('as_id', 'bool');
        $resolver->setAllowedTypes('accept', 'string[]');
        $resolver->setAllowedTypes('preview_preset', 'string');
        $resolver->setAllowedTypes('start_path', ['null', 'string']);
        $resolver->setNormalizer('accept', static fn (Options $options, array $accept): array => MimeTypePatterns::normalize($accept));
        $resolver->setInfo('accept', 'Accepted mime types: exact ("application/pdf") or families ("image/*"); none: every type of the library.');
        $resolver->setInfo('as_id', 'The data is the file id (list of ids when multiple) instead of the entity, e.g. for a JSON field.');
    }

    public function getBlockPrefix(): string
    {
        return 'gm_file_picker';
    }
}
