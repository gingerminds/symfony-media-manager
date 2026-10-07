<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/*
 * Service definitions of the bundle, split by concern under config/services/.
 */
return static function (ContainerConfigurator $container): void {
    foreach (['repository', 'storage', 'image', 'file', 'controller', 'api_platform', 'twig', 'command'] as $file) {
        $container->import(__DIR__ . '/services/' . $file . '.php');
    }
};
