<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\Command\File\MoveDirectoryCommand;
use Gingerminds\MediaManagerBundle\Command\Image\ClearImageCacheCommand;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaRepository;

/*
 * Console commands.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_media_manager.command.clear_image_cache', ClearImageCacheCommand::class)
        ->args([
            service(FileRepository::class),
            service(MediaRepository::class),
            service('gingerminds_media_manager.image.processor'),
            param('gingerminds_media_manager.storage.default_disk'),
        ])
        ->tag('console.command');

    $services->set('gingerminds_media_manager.command.move_directory', MoveDirectoryCommand::class)
        ->args([service('gingerminds_media_manager.file.library')])
        ->tag('console.command');
};
