<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\ApiPlatform\OpenApi\FilePresetOpenApiFactory;

/*
 * API Platform: OpenAPI documentation of the file endpoints.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_media_manager.api.openapi.file_preset', FilePresetOpenApiFactory::class)
        ->decorate('api_platform.openapi.factory')
        ->args([
            service('.inner'),
            param('gingerminds_media_manager.images.presets'),
        ]);
};
