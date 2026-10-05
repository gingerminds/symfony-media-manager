<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\Storage\DiskRegistry;

/*
 * Storage disks. The locator of the declared disks is set by the bundle from `storage.disks`.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_media_manager.storage.disk_registry', DiskRegistry::class)
        ->args([abstract_arg('disk locator'), param('gingerminds_media_manager.storage.default_disk')]);
    $services->alias(DiskRegistry::class, 'gingerminds_media_manager.storage.disk_registry');
};
