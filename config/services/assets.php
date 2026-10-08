<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gingerminds\MediaManagerBundle\AssetMapper\RelativeImportCompiler;

/*
 * Admin assets.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Before the Symfony import compiler, which then leaves the rewritten absolute imports alone.
    $services->set('gingerminds_media_manager.asset_mapper.relative_import_compiler', RelativeImportCompiler::class)
        ->args(['gingerminds-media-manager'])
        ->tag('asset_mapper.compiler', ['priority' => 10]);
};
