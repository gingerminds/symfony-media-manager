<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle;

use Gingerminds\CoreBundle\DependencyInjection\Compiler\OverriddenEntityPass;
use Gingerminds\MediaManagerBundle\Controller\File\FileLibraryController;
use Gingerminds\MediaManagerBundle\Controller\Media\MediaCategoryController;
use Gingerminds\MediaManagerBundle\Entity\File\File;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategory;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Gingerminds\MediaManagerBundle\File\Reference\FileReferenceSourceInterface;
use Gingerminds\MediaManagerBundle\File\Reference\FileUsageResolverInterface;
use Gingerminds\MediaManagerBundle\Form\Media\MediaCategoryType;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_locator;

/**
 * Media library, file library and image processing on top of GingermindsCoreBundle.
 */
final class GingermindsMediaManagerBundle extends AbstractBundle
{
    /**
     * Overridable entities of the bundle.
     */
    public const array RESOURCES = [
        'file' => [
            'entity' => File::class,
            'interface' => FileInterface::class,
        ],
        'media_category' => [
            'entity' => MediaCategory::class,
            'interface' => MediaCategoryInterface::class,
        ],
    ];

    /**
     * Admin resources, registered as `gingerminds_core` resources. Without `crud`, the controller
     * has its own routes (config/routes.php) instead of the core CRUD ones.
     */
    public const array ADMIN_RESOURCES = [
        'file' => [
            'controller' => FileLibraryController::class,
            'form' => null,
            'path' => 'files',
            'permission' => 'files',
            'crud' => false,
        ],
        'media_category' => [
            'controller' => MediaCategoryController::class,
            'form' => MediaCategoryType::class,
            'path' => 'media-categories',
            'permission' => 'media_categories',
        ],
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

        $container->services()->get('gingerminds_media_manager.storage.disk_registry')
            ->arg(0, service_locator(array_map(service(...), $config['storage']['disks'])));

        foreach (self::RESOURCES as $name => $resource) {
            $entity = $this->resourceValue($config, $name, 'entity');
            $parameters->set('gingerminds_media_manager.resource.' . $name . '.entity', $entity);

            if ($entity !== $resource['entity']) {
                OverriddenEntityPass::registerOverriddenEntity($builder, $resource['entity']);
            }
        }

        // The bundle routes (reorder, library) target the configured controller.
        foreach (array_keys(self::ADMIN_RESOURCES) as $name) {
            $parameters->set('gingerminds_media_manager.resource.' . $name . '.controller', $this->resourceValue($config, $name, 'controller'));
        }
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $config = $this->resolveConfig($builder);
        $resolveTargetEntities = [];

        foreach (self::RESOURCES as $name => $resource) {
            $resolveTargetEntities[$resource['interface']] = $this->resourceValue($config, $name, 'entity');
        }

        $builder->prependExtensionConfig('doctrine', [
            'orm' => [
                'resolve_target_entities' => $resolveTargetEntities,
                'mappings' => [
                    'GingermindsMediaManagerFile' => $this->mapping('File'),
                    'GingermindsMediaManagerMedia' => $this->mapping('Media'),
                ],
            ],
        ]);

        $resources = [];

        foreach (self::ADMIN_RESOURCES as $name => $resource) {
            $resources[$name] = [
                'entity' => $this->resourceValue($config, $name, 'entity'),
                'controller' => ($resource['crud'] ?? true) ? $this->resourceValue($config, $name, 'controller') : null,
                'form' => $this->resourceValue($config, $name, 'form'),
                'path' => $resource['path'],
                'permission' => $resource['permission'],
                'route_prefix' => 'gingerminds_media_manager_' . $name,
                'translation_prefix' => $name,
                'translation_domain' => self::TRANSLATION_DOMAIN,
                'template_prefix' => '@GingermindsMediaManager/pages/' . $name,
            ];
        }

        // Prepended: the project configuration still overrides any key.
        $builder->prependExtensionConfig('gingerminds_core', [
            'resources' => $resources,
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

    /**
     * @param array<string, mixed> $config
     */
    private function resourceValue(array $config, string $name, string $key): ?string
    {
        return $config['resources'][$name][$key] ?? self::RESOURCES[$name][$key] ?? self::ADMIN_RESOURCES[$name][$key];
    }

    /**
     * The bundle configuration needed while prepending: the other keys may hold env placeholders.
     *
     * @return array<string, mixed>
     */
    private function resolveConfig(ContainerBuilder $builder): array
    {
        $extension = $this->getContainerExtension();
        $configuration = $extension instanceof ConfigurationExtensionInterface ? $extension->getConfiguration([], $builder) : null;

        if (!$configuration instanceof ConfigurationInterface) {
            throw new LogicException('The GingermindsMediaManagerBundle configuration cannot be resolved.');
        }

        $configs = array_map(
            static fn (array $config): array => array_intersect_key($config, ['resources' => true, 'files_rate_limit' => true]),
            $builder->getExtensionConfig($this->extensionAlias),
        );

        return new Processor()->processConfiguration($configuration, $builder->getParameterBag()->resolveValue($configs));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapping(string $directory): array
    {
        return [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => $this->getPath() . '/src/Entity/' . $directory,
            'prefix' => 'Gingerminds\\MediaManagerBundle\\Entity\\' . $directory,
        ];
    }
}
