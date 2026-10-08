<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\Command\File\DeduplicateFilesCommand;
use Gingerminds\MediaManagerBundle\Command\File\HashFilesCommand;
use Gingerminds\MediaManagerBundle\Command\File\IndexFilesCommand;
use Gingerminds\MediaManagerBundle\Command\File\MoveDirectoryCommand;
use Gingerminds\MediaManagerBundle\Command\File\OrphanFilesCommand;
use Gingerminds\MediaManagerBundle\Command\File\RelocateFilesCommand;
use Gingerminds\MediaManagerBundle\Command\Image\ClearImageCacheCommand;
use Gingerminds\MediaManagerBundle\File\Maintenance\FileDeduplicator;
use Gingerminds\MediaManagerBundle\File\Maintenance\FileHasher;
use Gingerminds\MediaManagerBundle\File\Maintenance\FileIndexer;
use Gingerminds\MediaManagerBundle\File\Maintenance\FileRelocator;
use Gingerminds\MediaManagerBundle\File\Maintenance\OrphanFinder;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Gingerminds\MediaManagerBundle\Repository\Media\MediaRepository;

/*
 * Console commands.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_media_manager.command.clear_image_cache', ClearImageCacheCommand::class)
        ->args([
            service(FileRepository::class),
            service(MediaRepository::class),
            service('gingerminds_media_manager.image.processor'),
            param('gingerminds_media_manager.storage.default_disk'),
        ])
        ->tag('console.command');

    $services->set('gingerminds_media_manager.command.move_directory', MoveDirectoryCommand::class)
        ->args([service('gingerminds_media_manager.file.library')])
        ->tag('console.command');

    // Maintenance of the files (imported databases, files copied by hand).
    $services->set('gingerminds_media_manager.maintenance.hasher', FileHasher::class)
        ->args([service(FileRepository::class), service('gingerminds_media_manager.file.storage'), service('doctrine.orm.entity_manager')]);

    $services->set('gingerminds_media_manager.maintenance.indexer', FileIndexer::class)
        ->args([
            service(FileRepository::class),
            service('gingerminds_media_manager.storage.disk_registry'),
            service('gingerminds_media_manager.file.path_guard'),
            service('gingerminds_media_manager.file.mime_type_normalizer'),
            service('doctrine.orm.entity_manager'),
            param('gingerminds_media_manager.resource.file.entity'),
        ]);

    $services->set('gingerminds_media_manager.maintenance.deduplicator', FileDeduplicator::class)
        ->args([service(FileRepository::class), service('gingerminds_media_manager.file.library'), service('doctrine.orm.entity_manager')]);

    $services->set('gingerminds_media_manager.maintenance.relocator', FileRelocator::class)
        ->args([
            service(FileRepository::class),
            service('gingerminds_media_manager.file.storage'),
            service('gingerminds_media_manager.storage.disk_registry'),
            service('gingerminds_media_manager.file.path_guard'),
            service('gingerminds_media_manager.image.processor'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('gingerminds_media_manager.maintenance.orphan_finder', OrphanFinder::class)
        ->args([service(FileRepository::class), service('gingerminds_media_manager.file.reference_registry'), service('gingerminds_media_manager.file.library')]);

    $commands = [
        'hash_files' => [HashFilesCommand::class, 'hasher'],
        'index_files' => [IndexFilesCommand::class, 'indexer'],
        'deduplicate_files' => [DeduplicateFilesCommand::class, 'deduplicator'],
        'relocate_files' => [RelocateFilesCommand::class, 'relocator'],
        'orphan_files' => [OrphanFilesCommand::class, 'orphan_finder'],
    ];

    foreach ($commands as $name => [$class, $service]) {
        $services->set('gingerminds_media_manager.command.' . $name, $class)
            ->args([service('gingerminds_media_manager.maintenance.' . $service)])
            ->tag('console.command');
    }
};
