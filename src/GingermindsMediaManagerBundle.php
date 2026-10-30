<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle;

use Gingerminds\CoreBundle\Bundle\AbstractResourceBundle;
use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Gingerminds\MediaManagerBundle\Basket\Entity\Basket;
use Gingerminds\MediaManagerBundle\Basket\Entity\BasketInterface;
use Gingerminds\MediaManagerBundle\Controller\File\FileLibraryController;
use Gingerminds\MediaManagerBundle\Controller\Media\MediaCategoryController;
use Gingerminds\MediaManagerBundle\Controller\Media\MediaController;
use Gingerminds\MediaManagerBundle\Entity\File\File;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\Media;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategory;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;
use Gingerminds\MediaManagerBundle\File\Reference\FileReferenceSourceInterface;
use Gingerminds\MediaManagerBundle\File\Reference\FileUsageResolverInterface;
use Gingerminds\MediaManagerBundle\Form\Media\MediaCategoryType;
use Gingerminds\MediaManagerBundle\Form\Media\MediaType;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_locator;

/**
 * Media library, file library and image processing on top of GingermindsCoreBundle.
 */
final class GingermindsMediaManagerBundle extends AbstractResourceBundle
{
    /**
     * Overridable entities and admin resources of the bundle (AbstractResourceBundle::resources()).
     */
    public const array RESOURCES = [
        'file' => [
            'entity' => File::class,
            'interface' => FileInterface::class,
            'controller' => FileLibraryController::class,
            'path' => 'files',
            'permission' => 'files',
            'permissions' => [ResourceVoter::DELETE => 'edit files'],
        ],
        'media_category' => [
            'entity' => MediaCategory::class,
            'interface' => MediaCategoryInterface::class,
            'controller' => MediaCategoryController::class,
            'form' => MediaCategoryType::class,
            'path' => 'media-categories',
            'permission' => 'media_categories',
        ],
        'media' => [
            'entity' => Media::class,
            'interface' => MediaInterface::class,
            'controller' => MediaController::class,
            'form' => MediaType::class,
            'path' => 'medias',
            'permission' => 'medias',
        ],
    ];

    /**
     * Overridable entity of the baskets, without admin, only declared when `basket.enabled`.
     */
    public const array BASKET_RESOURCE = [
        'entity' => Basket::class,
        'interface' => BasketInterface::class,
        'admin' => false,
    ];

    public const string TRANSLATION_DOMAIN = 'GingermindsMediaManager';

    public const string DEFAULT_STORAGE = 'gingerminds_media_manager.storage.default';

    public const string FILES_RATE_LIMITER = 'gingerminds_media_manager_files';

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

        $builder->registerForAutoconfiguration(FileReferenceSourceInterface::class)->addTag(FileReferenceSourceInterface::TAG);
        $builder->registerForAutoconfiguration(FileUsageResolverInterface::class)->addTag(FileUsageResolverInterface::TAG);

        $parameters = $container->parameters();
        $parameters->set('gingerminds_media_manager.storage.default_disk', $config['storage']['default_disk']);
        $parameters->set('gingerminds_media_manager.library.root', $config['library']['root']);
        $parameters->set('gingerminds_media_manager.library.max_upload_size', $config['library']['max_upload_size']);
        $parameters->set('gingerminds_media_manager.library.allowed_mimes', $config['library']['allowed_mimes']);
        $parameters->set('gingerminds_media_manager.library.per_page', $config['library']['per_page']);
        $parameters->set('gingerminds_media_manager.library.max_directory_move', $config['library']['max_directory_move']);
        $parameters->set('gingerminds_media_manager.images.driver', $config['images']['driver']);
        $parameters->set('gingerminds_media_manager.images.default_format', $config['images']['default_format']);
        $parameters->set('gingerminds_media_manager.images.cache_prefix', $config['images']['cache_prefix']);
        $parameters->set('gingerminds_media_manager.images.presets', $config['images']['presets']);
        $parameters->set('gingerminds_media_manager.basket.claim_strategy', $config['basket']['claim_strategy']);
        $parameters->set('gingerminds_media_manager.basket.ttl', $config['basket']['ttl']);
        $parameters->set('gingerminds_media_manager.basket.delete_after_download', $config['basket']['delete_after_download']);

        if ($config['basket']['enabled']) {
            $container->import('../config/services/basket.php');
            // A media in a basket can still be deleted.
            $container->services()->get('gingerminds_media_manager.media.usage_counter')->arg(2, [BasketInterface::class]);
        }

        $container->services()->get('gingerminds_media_manager.storage.disk_registry')
            ->arg(0, service_locator(array_map(service(...), $config['storage']['disks'])));

        parent::loadExtension($config, $container, $builder);
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::prependExtension($container, $builder);

        $config = $this->resolveConfig($builder);

        // Outside src/Entity: API Platform would find the basket resource there even when disabled.
        if ($config['basket']['enabled']) {
            $builder->prependExtensionConfig('doctrine', ['orm' => ['mappings' => [
                'GingermindsMediaManagerBasket' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => $this->getPath() . '/src/Basket/Entity',
                    'prefix' => 'Gingerminds\\MediaManagerBundle\\Basket\\Entity',
                ],
            ]]]);
            $builder->prependExtensionConfig('api_platform', ['mapping' => ['paths' => [$this->getPath() . '/src/Basket/Entity']]]);
        }

        // Prepended: the project configuration still overrides any key.
        $builder->prependExtensionConfig('gingerminds_core', [
            'admin_includes' => ['head' => ['@GingermindsMediaManager/admin/_head.html.twig']],
        ]);

        $builder->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [$this->getPath() . '/assets' => 'gingerminds-media-manager'],
            ],
            'rate_limiter' => [
                self::FILES_RATE_LIMITER => 0 === $config['files_rate_limit']
                    ? ['policy' => 'no_limit']
                    : ['policy' => 'fixed_window', 'limit' => $config['files_rate_limit'], 'interval' => '1 minute'],
            ],
        ]);

        $builder->prependExtensionConfig('twig', [
            'form_themes' => ['@GingermindsMediaManager/form/file_picker_theme.html.twig', '@GingermindsMediaManager/form/media_select_theme.html.twig'],
        ]);

        // Compiled with the core admin stylesheet, its load paths (project theme, Bootstrap) included.
        if ($builder->hasExtension('symfonycasts_sass')) {
            $builder->prependExtensionConfig('symfonycasts_sass', [
                'root_sass' => [$this->getPath() . '/assets/styles/media-manager.scss'],
            ]);
        }

        // A project storage with the same name replaces this one entirely.
        $builder->prependExtensionConfig('flysystem', [
            'storages' => [
                self::DEFAULT_STORAGE => ['local' => ['directory' => '%kernel.project_dir%/var/storage/media']],
            ],
        ]);
    }

    protected function resources(array $config): array
    {
        return $config['basket']['enabled'] ? [...self::RESOURCES, 'basket' => self::BASKET_RESOURCE] : self::RESOURCES;
    }

    protected function entityDirectories(): array
    {
        return ['File', 'Media'];
    }

    protected function prependConfigKeys(): array
    {
        return ['resources', 'basket', 'files_rate_limit'];
    }
}
