<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\Controller\File\ShowFileController;
use Gingerminds\MediaManagerBundle\Controller\File\ShowFilePresetController;
use Gingerminds\MediaManagerBundle\Controller\Media\MediaCategoryController;
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
};
