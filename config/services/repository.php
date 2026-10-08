<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaCategoryRepository;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaRepository;

/*
 * Repositories. Doctrine requires the FQCN as service id: `gingerminds_media_manager.repository.*` are aliases.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(FileRepository::class)
        ->args([service('doctrine'), param('gingerminds_media_manager.resource.file.entity')])
        ->call('setFilterHandlerRegistry', [service('gingerminds_core.filter_handler_registry')])
        ->tag('doctrine.repository_service');
    $services->alias('gingerminds_media_manager.repository.file', FileRepository::class);

    $services->set(MediaCategoryRepository::class)
        ->args([service('doctrine'), param('gingerminds_media_manager.resource.media_category.entity')])
        ->call('setFilterHandlerRegistry', [service('gingerminds_core.filter_handler_registry')])
        ->tag('doctrine.repository_service');
    $services->alias('gingerminds_media_manager.repository.media_category', MediaCategoryRepository::class);

    $services->set(MediaRepository::class)
        ->args([service('doctrine'), param('gingerminds_media_manager.resource.media.entity')])
        ->call('setFilterHandlerRegistry', [service('gingerminds_core.filter_handler_registry')])
        ->tag('doctrine.repository_service');
    $services->alias('gingerminds_media_manager.repository.media', MediaRepository::class);
};
