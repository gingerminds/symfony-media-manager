<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\File\FileNamerInterface;
use Gingerminds\MediaManagerBundle\File\FileStorage;
use Gingerminds\MediaManagerBundle\File\MimeTypeGuesser;
use Gingerminds\MediaManagerBundle\File\PathGuard;
use Gingerminds\MediaManagerBundle\File\PathLocker;
use Gingerminds\MediaManagerBundle\File\SlugFileNamer;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Symfony\Component\Mime\MimeTypes;

/*
 * Files: paths, mime types, path locks and storage.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Alias FileNamerInterface to your service for another naming rule.
    $services->set('gingerminds_media_manager.file.namer', SlugFileNamer::class)
        ->args([service('slugger')]);
    $services->alias(FileNamerInterface::class, 'gingerminds_media_manager.file.namer');

    $services->set('gingerminds_media_manager.file.path_guard', PathGuard::class)
        ->args([param('gingerminds_media_manager.library.root'), service(FileNamerInterface::class)]);
    $services->alias(PathGuard::class, 'gingerminds_media_manager.file.path_guard');

    // Store of framework.lock (semaphore or flock by default: one server only).
    $services->set('gingerminds_media_manager.file.path_locker', PathLocker::class)
        ->args([service('lock.factory')]);
    $services->alias(PathLocker::class, 'gingerminds_media_manager.file.path_locker');

    $services->set('gingerminds_media_manager.file.mime_types', MimeTypes::class);

    $services->set('gingerminds_media_manager.file.mime_type_guesser', MimeTypeGuesser::class)
        ->args([service('gingerminds_media_manager.file.mime_types')]);
    $services->alias(MimeTypeGuesser::class, 'gingerminds_media_manager.file.mime_type_guesser');

    $services->set('gingerminds_media_manager.file.storage', FileStorage::class)
        ->args([
            service('gingerminds_media_manager.storage.disk_registry'),
            service('gingerminds_media_manager.file.path_guard'),
            service('gingerminds_media_manager.file.mime_type_guesser'),
            service(FileRepository::class),
            service('gingerminds_media_manager.image.processor'),
            param('gingerminds_media_manager.resource.file.entity'),
            param('gingerminds_media_manager.library.max_upload_size'),
            param('gingerminds_media_manager.library.allowed_mimes'),
            service('gingerminds_media_manager.file.path_locker'),
            service('logger')->ignoreOnInvalid(),
        ]);
    $services->alias(FileStorage::class, 'gingerminds_media_manager.file.storage');
};
