<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\Http\FileResponseFactory;
use Gingerminds\MediaManagerBundle\Image\ImageProcessor;

/*
 * Image presets (Glide) and file responses.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_media_manager.image.processor', ImageProcessor::class)
        ->args([
            service('gingerminds_media_manager.storage.disk_registry'),
            param('gingerminds_media_manager.images.driver'),
            param('gingerminds_media_manager.images.default_format'),
            param('gingerminds_media_manager.images.cache_prefix'),
            param('gingerminds_media_manager.images.presets'),
        ]);
    $services->alias(ImageProcessor::class, 'gingerminds_media_manager.image.processor');

    $services->set('gingerminds_media_manager.http.file_response_factory', FileResponseFactory::class)
        ->args([service('gingerminds_media_manager.storage.disk_registry')]);
    $services->alias(FileResponseFactory::class, 'gingerminds_media_manager.http.file_response_factory');
};
