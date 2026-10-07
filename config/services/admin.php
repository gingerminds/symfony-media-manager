<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\File\LibraryStartPathProviderInterface;
use Gingerminds\MediaManagerBundle\Form\File\FilePickerType;
use Gingerminds\MediaManagerBundle\Form\Media\MediaCategoryType;
use Gingerminds\MediaManagerBundle\Menu\MediaManagerAdminMenuProvider;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaCategoryRepository;
use Gingerminds\MediaManagerBundle\Security\Voter\FileVoter;
use Gingerminds\MediaManagerBundle\Security\Voter\MediaCategoryVoter;

/*
 * Admin: menu, voters and form types.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_media_manager.admin_menu.provider', MediaManagerAdminMenuProvider::class)
        ->args([service('gingerminds_core.resource_registry')])
        ->tag('gingerminds_core.admin_menu_provider');

    $services->set('gingerminds_media_manager.security.voter.media_category', MediaCategoryVoter::class)
        ->tag('security.voter');

    $services->set('gingerminds_media_manager.security.voter.file', FileVoter::class)
        ->tag('security.voter');

    $services->set('gingerminds_media_manager.form.type.media_category', MediaCategoryType::class)
        ->args([service('gingerminds_core.resource_registry'), service(MediaCategoryRepository::class)])
        ->tag('form.type');

    $services->set('gingerminds_media_manager.form.type.file_picker', FilePickerType::class)
        ->args([
            service(FileRepository::class),
            service('gingerminds_media_manager.file.library_presenter'),
            service('router'),
            service('security.authorization_checker'),
            service(LibraryStartPathProviderInterface::class),
            param('gingerminds_media_manager.images.presets'),
        ])
        ->tag('form.type');
    $services->alias(FilePickerType::class, 'gingerminds_media_manager.form.type.file_picker');
};
