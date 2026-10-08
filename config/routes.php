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

    $reads = '%gingerminds_media_manager.resource.file.controller%';
    $writes = 'gingerminds_media_manager.controller.admin.file_library_action';
    $actions = [
        'browse' => ['/browse', $reads, 'browse', 'GET'],
        'directories' => ['/directories', $reads, 'directories', 'GET'],
        'picker' => ['/picker', $reads, 'picker', 'GET'],
        'show' => ['/{id}', $reads, 'show', 'GET'],
        'directory_create' => ['/directories', $writes, 'createDirectory', 'POST'],
        'directory_delete' => ['/directories', $writes, 'deleteDirectory', 'DELETE'],
        'directory_move' => ['/directories', $writes, 'moveDirectory', 'PATCH'],
        'upload' => ['/upload', $writes, 'upload', 'POST'],
        'move' => ['/move', $writes, 'move', 'POST'],
        'delete' => ['/delete', $writes, 'delete', 'POST'],
        'merge' => ['/merge', $writes, 'merge', 'POST'],
        'rename' => ['/{id}', $writes, 'rename', 'PATCH'],
    ];

    foreach ($actions as $name => [$path, $controller, $action, $method]) {
        $files->add($name, $path)
            ->controller($controller . '::' . $action)
            ->methods([$method])
            ->requirements(['id' => Requirement::UUID]);
    }
};
