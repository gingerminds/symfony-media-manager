<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\Controller\File\FileLibraryActionController;
use Gingerminds\MediaManagerBundle\Controller\File\FileLibraryController;
use Gingerminds\MediaManagerBundle\Controller\File\ShowFileController;
use Gingerminds\MediaManagerBundle\Controller\File\ShowFilePresetController;
use Gingerminds\MediaManagerBundle\Controller\Media\MediaCategoryController;
use Gingerminds\MediaManagerBundle\Controller\Media\MediaController;
use Gingerminds\MediaManagerBundle\File\LibraryStartPathProviderInterface;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;

/*
 * Controllers.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_media_manager.controller.file.show', ShowFileController::class)
        ->args([service(FileRepository::class), service('gingerminds_media_manager.http.file_response_factory')])
        ->tag('controller.service_arguments')
        ->public();

    $services->set('gingerminds_media_manager.controller.file.show_preset', ShowFilePresetController::class)
        ->args([
            service(FileRepository::class),
            service('gingerminds_media_manager.http.file_response_factory'),
            service('gingerminds_media_manager.image.processor'),
        ])
        ->tag('controller.service_arguments')
        ->public();

    $services->set('gingerminds_media_manager.controller.admin.media_category', MediaCategoryController::class)
        ->args([service('gingerminds_core.controller.context')])
        ->tag('controller.service_arguments');
    $services->alias(MediaCategoryController::class, 'gingerminds_media_manager.controller.admin.media_category')->public();

    $services->set('gingerminds_media_manager.controller.admin.media', MediaController::class)
        ->args([service('gingerminds_core.controller.context')])
        ->tag('controller.service_arguments');
    $services->alias(MediaController::class, 'gingerminds_media_manager.controller.admin.media')->public();

    $services->set('gingerminds_media_manager.controller.admin.file_library', FileLibraryController::class)
        ->args([
            service('gingerminds_core.controller.context'),
            service('gingerminds_media_manager.file.library'),
            service(FileRepository::class),
            service('gingerminds_media_manager.file.library_presenter'),
            service('gingerminds_media_manager.file.reference_registry'),
            service(LibraryStartPathProviderInterface::class),
            param('gingerminds_media_manager.library.max_upload_size'),
            param('gingerminds_media_manager.library.allowed_mimes'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(FileLibraryController::class, 'gingerminds_media_manager.controller.admin.file_library')->public();

    // Decorate this service to change the write actions of the library.
    $services->set('gingerminds_media_manager.controller.admin.file_library_action', FileLibraryActionController::class)
        ->args([
            service('gingerminds_core.controller.context'),
            service('gingerminds_media_manager.file.library'),
            service(FileRepository::class),
            service('gingerminds_media_manager.file.library_presenter'),
            param('gingerminds_media_manager.library.max_directory_move'),
        ])
        ->tag('controller.service_arguments')
        ->public();
};
