<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Requirement\Requirement;

// Extra routes only: the CRUD routes come from the core `gingerminds_crud` loader.
return static function (RoutingConfigurator $routes): void {
    $routes->add('gingerminds_media_manager_media_category_reorder', '/%gingerminds_core.admin_prefix%/media-categories/reorder')
        ->controller('%gingerminds_media_manager.resource.media_category.controller%::reorder')
        ->methods(['POST']);

    $routes->add('gingerminds_media_manager_file_index', '/%gingerminds_core.admin_prefix%/files')
        ->controller('%gingerminds_media_manager.resource.file.controller%::index')
        ->methods(['GET']);

    $files = $routes->collection('gingerminds_media_manager_file_')
        ->prefix('/%gingerminds_core.admin_prefix%/files');

    $actions = [
        'browse' => ['/browse', 'browse', 'GET'],
        'directories' => ['/directories', 'directories', 'GET'],
        'directory_create' => ['/directories', 'createDirectory', 'POST'],
        'directory_delete' => ['/directories', 'deleteDirectory', 'DELETE'],
        'upload' => ['/upload', 'upload', 'POST'],
        'move' => ['/move', 'move', 'POST'],
        'delete' => ['/delete', 'delete', 'POST'],
        'merge' => ['/merge', 'merge', 'POST'],
        'show' => ['/{id}', 'show', 'GET'],
        'rename' => ['/{id}', 'rename', 'PATCH'],
    ];

    foreach ($actions as $name => [$path, $action, $method]) {
        $files->add($name, $path)
            ->controller('%gingerminds_media_manager.resource.file.controller%::' . $action)
            ->methods([$method])
            ->requirements(['id' => Requirement::UUID]);
    }
};
