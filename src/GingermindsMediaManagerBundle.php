<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Media library, file library and image processing on top of GingermindsCoreBundle.
 */
final class GingermindsMediaManagerBundle extends AbstractBundle
{
    public const string TRANSLATION_DOMAIN = 'GingermindsMediaManager';

    protected string $extensionAlias = 'gingerminds_media_manager';

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/definition.php');
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $parameters = $container->parameters();
        $parameters->set('gingerminds_media_manager.storage.default_disk', $config['storage']['default_disk']);
        $parameters->set('gingerminds_media_manager.storage.disks', $config['storage']['disks']);
        $parameters->set('gingerminds_media_manager.library.root', $config['library']['root']);
        $parameters->set('gingerminds_media_manager.library.max_upload_size', $config['library']['max_upload_size']);
        $parameters->set('gingerminds_media_manager.library.allowed_mimes', $config['library']['allowed_mimes']);
        $parameters->set('gingerminds_media_manager.library.per_page', $config['library']['per_page']);
        $parameters->set('gingerminds_media_manager.images.driver', $config['images']['driver']);
        $parameters->set('gingerminds_media_manager.images.default_format', $config['images']['default_format']);
        $parameters->set('gingerminds_media_manager.images.cache_prefix', $config['images']['cache_prefix']);
        $parameters->set('gingerminds_media_manager.images.presets', $config['images']['presets']);
        $parameters->set('gingerminds_media_manager.files_rate_limit', $config['files_rate_limit']);
        $parameters->set('gingerminds_media_manager.basket.enabled', $config['basket']['enabled']);
        $parameters->set('gingerminds_media_manager.basket.claim_strategy', $config['basket']['claim_strategy']);
    }
}
