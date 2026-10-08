<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\Media\MediaPresenter;
use Gingerminds\MediaManagerBundle\Media\MediaUsageCounter;

/*
 * Medias: presenter of the picker, usages.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_media_manager.media.presenter', MediaPresenter::class)
        ->args([service('gingerminds_media_manager.file.library_presenter')]);

    $services->set('gingerminds_media_manager.media.usage_counter', MediaUsageCounter::class)
        ->args([service('doctrine.orm.entity_manager')]);
    $services->alias(MediaUsageCounter::class, 'gingerminds_media_manager.media.usage_counter');
};
