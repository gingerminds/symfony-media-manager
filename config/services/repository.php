<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;

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
};
