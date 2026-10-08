<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\Twig\MediaManagerExtension;

/*
 * Twig functions.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_media_manager.twig.extension', MediaManagerExtension::class)
        ->args([service('router')])
        ->tag('twig.extension');
};
