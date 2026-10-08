<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\File\DirectoryMover;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\File\FileLibraryPresenter;
use Gingerminds\MediaManagerBundle\File\LibraryStartPathProviderInterface;
use Gingerminds\MediaManagerBundle\File\Reference\DoctrineAssociationReferenceSource;
use Gingerminds\MediaManagerBundle\File\Reference\FileReferenceRegistry;
use Gingerminds\MediaManagerBundle\File\Reference\FileReferenceSourceInterface;
use Gingerminds\MediaManagerBundle\File\Reference\FileUsageResolverInterface;
use Gingerminds\MediaManagerBundle\File\Reference\ResourceFileUsageResolver;
use Gingerminds\MediaManagerBundle\File\RootLibraryStartPathProvider;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;

/*
 * File library and file reference registry.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_media_manager.file.reference.doctrine_associations', DoctrineAssociationReferenceSource::class)
        ->args([service('doctrine.orm.entity_manager')])
        ->tag(FileReferenceSourceInterface::TAG);

    // Last resort: the owner itself.
    $services->set('gingerminds_media_manager.file.reference.resource_resolver', ResourceFileUsageResolver::class)
        ->args([service('gingerminds_core.resource_registry'), service('router'), service('translator')])
        ->tag(FileUsageResolverInterface::TAG, ['priority' => -1000]);

    $services->set('gingerminds_media_manager.file.reference_registry', FileReferenceRegistry::class)
        ->args([tagged_iterator(FileReferenceSourceInterface::TAG), tagged_iterator(FileUsageResolverInterface::TAG)]);
    $services->alias(FileReferenceRegistry::class, 'gingerminds_media_manager.file.reference_registry');

    $services->set('gingerminds_media_manager.file.directory_mover', DirectoryMover::class)
        ->args([
            service(FileRepository::class),
            service('gingerminds_media_manager.storage.disk_registry'),
            service('gingerminds_media_manager.file.path_guard'),
            service('gingerminds_media_manager.image.processor'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('gingerminds_media_manager.file.library', FileLibrary::class)
        ->args([
            service('gingerminds_media_manager.file.storage'),
            service(FileRepository::class),
            service('gingerminds_media_manager.file.reference_registry'),
            service('gingerminds_media_manager.storage.disk_registry'),
            service('gingerminds_media_manager.file.path_guard'),
            service('gingerminds_media_manager.image.processor'),
            service('doctrine.orm.entity_manager'),
            service('gingerminds_media_manager.file.directory_mover'),
            param('gingerminds_media_manager.library.per_page'),
        ]);
    $services->alias(FileLibrary::class, 'gingerminds_media_manager.file.library');

    $services->set('gingerminds_media_manager.file.library_presenter', FileLibraryPresenter::class)
        ->args([
            service('router'),
            service('gingerminds_media_manager.file.reference_registry'),
            service('gingerminds_media_manager.file.path_guard'),
            param('gingerminds_media_manager.images.presets'),
        ]);

    // Replace the alias to open the browser elsewhere (e.g. the folder of the current site).
    $services->set('gingerminds_media_manager.file.root_start_path_provider', RootLibraryStartPathProvider::class);
    $services->alias(LibraryStartPathProviderInterface::class, 'gingerminds_media_manager.file.root_start_path_provider');
};
