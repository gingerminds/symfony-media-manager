<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Unit\DependencyInjection;

use Gingerminds\CoreBundle\DependencyInjection\Compiler\OverriddenEntityPass;
use Gingerminds\CoreBundle\DependencyInjection\Compiler\ResourceRegistryPass;
use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Gingerminds\MediaManagerBundle\Basket\Entity\Basket;
use Gingerminds\MediaManagerBundle\Basket\Entity\BasketInterface;
use Gingerminds\MediaManagerBundle\Controller\File\FileLibraryController;
use Gingerminds\MediaManagerBundle\Controller\Media\MediaCategoryController;
use Gingerminds\MediaManagerBundle\Entity\File\File;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\Media;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategory;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;
use Gingerminds\MediaManagerBundle\Form\Media\MediaCategoryType;
use Gingerminds\MediaManagerBundle\Form\Media\MediaType;
use Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle;
use Gingerminds\MediaManagerBundle\Tests\Application\Override\File as ProjectFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

final class BundleConfigurationTest extends TestCase
{
    public function testDefaultsArePrependedToDoctrineAndFlysystem(): void
    {
        $container = $this->prepend([]);

        $doctrine = $this->merged($container, 'doctrine')['orm'];
        self::assertSame(
            [FileInterface::class => File::class, MediaCategoryInterface::class => MediaCategory::class, MediaInterface::class => Media::class, BasketInterface::class => Basket::class],
            $doctrine['resolve_target_entities'],
        );
        self::assertSame(['GingermindsMediaManagerBasket', 'GingermindsMediaManagerFile', 'GingermindsMediaManagerMedia'], array_keys($doctrine['mappings']));
        self::assertSame('Gingerminds\MediaManagerBundle\Basket\Entity', $doctrine['mappings']['GingermindsMediaManagerBasket']['prefix']);
        self::assertSame([new GingermindsMediaManagerBundle()->getPath() . '/src/Basket/Entity'], $this->merged($container, 'api_platform')['mapping']['paths']);
        self::assertSame('Gingerminds\MediaManagerBundle\Entity\File', $doctrine['mappings']['GingermindsMediaManagerFile']['prefix']);

        self::assertSame(
            ['local' => ['directory' => '%kernel.project_dir%/var/storage/media']],
            $this->merged($container, 'flysystem')['storages'][GingermindsMediaManagerBundle::DEFAULT_STORAGE],
        );
    }

    public function testTheMediaCategoriesAreRegisteredOnLoad(): void
    {
        self::assertArrayNotHasKey('resources', $this->merged($this->prepend([]), 'gingerminds_core'), 'Registered on load (ResourceRegistryPass), below the project configuration.');

        self::assertSame([
            'entity' => MediaCategory::class,
            'controller' => MediaCategoryController::class,
            'form' => MediaCategoryType::class,
            'path' => 'media-categories',
            'permission' => 'media_categories',
            'route_prefix' => 'gingerminds_media_manager_media_category',
            'translation_prefix' => 'media_category',
            'translation_domain' => 'GingermindsMediaManager',
            'template_prefix' => '@GingermindsMediaManager/pages/media_category',
        ], $this->bundleResource($this->load([]), 'media_category'));

        $configs = [['resources' => ['media_category' => ['controller' => 'App\\Controller\\CategoryController']]]];
        self::assertSame('App\\Controller\\CategoryController', $this->bundleResource($this->load($configs), 'media_category')['controller']);
    }

    public function testADisabledBasketIsNeitherMappedNorAnApiResource(): void
    {
        $container = $this->prepend([['basket' => ['enabled' => false]]]);
        $doctrine = $this->merged($container, 'doctrine')['orm'];

        self::assertArrayNotHasKey(BasketInterface::class, $doctrine['resolve_target_entities']);
        self::assertArrayNotHasKey('GingermindsMediaManagerBasket', $doctrine['mappings']);
        self::assertSame([], $container->getExtensionConfig('api_platform'));
    }

    public function testTheMediasAreRegisteredOnLoad(): void
    {
        $resource = $this->bundleResource($this->load([]), 'media');

        self::assertSame(Media::class, $resource['entity']);
        self::assertSame(MediaType::class, $resource['form']);
        self::assertSame('medias', $resource['path']);
        self::assertSame('medias', $resource['permission']);
        self::assertSame('gingerminds_media_manager_media', $resource['route_prefix']);
    }

    public function testTheFilesAreDeletedWithTheEditPermission(): void
    {
        $resource = $this->bundleResource($this->load([]), 'file');

        self::assertSame(FileLibraryController::class, $resource['controller']);
        self::assertSame('files', $resource['permission']);
        self::assertSame([ResourceVoter::DELETE => 'edit files'], $resource['permissions']);
    }

    public function testTheBasketIsAnEntityWithoutAdmin(): void
    {
        $container = $this->load([]);

        self::assertSame(Basket::class, $container->getParameter('gingerminds_media_manager.resource.basket.entity'));
        self::assertFalse($container->hasDefinition(ResourceRegistryPass::BUNDLE_RESOURCE_TAG . '.basket'));
    }

    public function testThePurgeIsADailyTaskWithTheScheduler(): void
    {
        $tags = $this->load([])->getDefinition('gingerminds_media_manager.command.purge_baskets')->getTag('scheduler.task');

        self::assertSame('1 day', $tags[0]['frequency'] ?? null);
        self::assertSame('every', $tags[0]['trigger'] ?? null);
    }

    public function testTheAdminAssetsArePrepended(): void
    {
        $container = $this->prepend([]);
        $bundleDir = new GingermindsMediaManagerBundle()->getPath();

        self::assertSame(['head' => ['@GingermindsMediaManager/admin/_head.html.twig']], $this->merged($container, 'gingerminds_core')['admin_includes']);
        self::assertSame([$bundleDir . '/assets' => 'gingerminds-media-manager'], $this->merged($container, 'framework')['asset_mapper']['paths']);
    }

    public function testAProjectEntityOverridesTheBundleOne(): void
    {
        $configs = [['resources' => ['file' => ['entity' => ProjectFile::class]]]];
        $container = $this->prepend($configs);

        self::assertSame(ProjectFile::class, $this->merged($container, 'doctrine')['orm']['resolve_target_entities'][FileInterface::class]);

        new GingermindsMediaManagerBundle()->getContainerExtension()?->load($configs, $container);

        self::assertSame(ProjectFile::class, $container->getParameter('gingerminds_media_manager.resource.file.entity'));
        $overridden = array_merge(...array_values(array_map(static fn (array $tags): array => array_column($tags, 'class'), $container->findTaggedServiceIds(OverriddenEntityPass::TAG))));
        self::assertSame([File::class], $overridden);
    }

    public function testAnEnvPlaceholderDoesNotBreakThePrepend(): void
    {
        $container = $this->prepend([['library' => ['root' => '%env(FILE_LIBRARY_ROOT)%'], 'storage' => ['default_disk' => '%env(FILE_LIBRARY_DISK)%']]]);

        self::assertSame(File::class, $this->merged($container, 'doctrine')['orm']['resolve_target_entities'][FileInterface::class]);
    }

    /**
     * @param list<array<string, mixed>> $configs gingerminds_media_manager configurations
     */
    private function prepend(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $container->setParameter('kernel.bundles_metadata', []);

        $extension = new GingermindsMediaManagerBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container->registerExtension($extension);

        foreach ($configs as $config) {
            $container->loadFromExtension('gingerminds_media_manager', $config);
        }

        $extension->prepend($container);

        return $container;
    }

    /**
     * @param list<array<string, mixed>> $configs gingerminds_media_manager configurations
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = $this->prepend($configs);
        new GingermindsMediaManagerBundle()->getContainerExtension()?->load($configs, $container);

        return $container;
    }

    /**
     * @return array<string, mixed>
     */
    private function bundleResource(ContainerBuilder $container, string $name): array
    {
        $attributes = $container->getDefinition(ResourceRegistryPass::BUNDLE_RESOURCE_TAG . '.' . $name)->getTag(ResourceRegistryPass::BUNDLE_RESOURCE_TAG)[0];
        unset($attributes['resource']);

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function merged(ContainerBuilder $container, string $extension): array
    {
        return array_replace_recursive([], ...$container->getExtensionConfig($extension));
    }
}
