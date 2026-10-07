<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\CoreBundle\ApiPlatform\State\ResourceProvider;
use Gingerminds\MediaManagerBundle\ApiPlatform\OpenApi\FilePresetOpenApiFactory;
use Gingerminds\MediaManagerBundle\ApiPlatform\State\MediaCategoryTreeProvider;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaCategoryRepository;

/*
 * API Platform: state providers and OpenAPI documentation of the file endpoints.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_media_manager.api.provider.media_category', ResourceProvider::class)
        ->args([service(MediaCategoryRepository::class), service('request_stack')])
        ->tag('api_platform.state_provider');

    $services->set('gingerminds_media_manager.api.provider.media_category_tree', MediaCategoryTreeProvider::class)
        ->args([service(MediaCategoryRepository::class)])
        ->tag('api_platform.state_provider');

    $services->set('gingerminds_media_manager.api.openapi.file_preset', FilePresetOpenApiFactory::class)
        ->decorate('api_platform.openapi.factory')
        ->args([
            service('.inner'),
            param('gingerminds_media_manager.images.presets'),
        ]);
};
