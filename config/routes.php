<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

// Extra routes only: the CRUD routes come from the core `gingerminds_crud` loader.
return static function (RoutingConfigurator $routes): void {
    $routes->add('gingerminds_media_manager_media_category_reorder', '/%gingerminds_core.admin_prefix%/media-categories/reorder')
        ->controller('%gingerminds_media_manager.resource.media_category.controller%::reorder')
        ->methods(['POST']);
};
