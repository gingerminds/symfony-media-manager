<?php

declare(strict_types=1);

use Gingerminds\MediaManagerBundle\Controller\File\FileLibraryActionController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

// The resource routes (media, media categories, file library reads) come from the core `gingerminds_crud` loader.
return static function (RoutingConfigurator $routes): void {
    $routes->import(FileLibraryActionController::class, 'attribute')->prefix('/%gingerminds_core.admin_prefix%');
};
