<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\File\FileStorage;
use Gingerminds\MediaManagerBundle\File\MimeTypeNormalizer;
use Gingerminds\MediaManagerBundle\File\PathGuard;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Symfony\Component\Mime\MimeTypes;

/*
 * Files: paths, mime types and storage.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gingerminds_media_manager.file.path_guard', PathGuard::class)
        ->args([param('gingerminds_media_manager.library.root'), service('slugger')]);
    $services->alias(PathGuard::class, 'gingerminds_media_manager.file.path_guard');

    $services->set('gingerminds_media_manager.file.mime_types', MimeTypes::class);

    $services->set('gingerminds_media_manager.file.mime_type_normalizer', MimeTypeNormalizer::class)
        ->args([service('gingerminds_media_manager.file.mime_types')]);
    $services->alias(MimeTypeNormalizer::class, 'gingerminds_media_manager.file.mime_type_normalizer');

    $services->set('gingerminds_media_manager.file.storage', FileStorage::class)
        ->args([
            service('gingerminds_media_manager.storage.disk_registry'),
            service('gingerminds_media_manager.file.path_guard'),
            service('gingerminds_media_manager.file.mime_type_normalizer'),
            service(FileRepository::class),
            param('gingerminds_media_manager.resource.file.entity'),
            param('gingerminds_media_manager.library.max_upload_size'),
            param('gingerminds_media_manager.library.allowed_mimes'),
        ]);
    $services->alias(FileStorage::class, 'gingerminds_media_manager.file.storage');
};
