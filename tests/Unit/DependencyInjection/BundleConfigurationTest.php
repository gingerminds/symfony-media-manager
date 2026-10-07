<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Unit\DependencyInjection;

use Gingerminds\CoreBundle\DependencyInjection\Compiler\OverriddenEntityPass;
use Gingerminds\MediaManagerBundle\Entity\File\File;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategory;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategoryInterface;
use Gingerminds\MediaManagerBundle\Form\Media\MediaCategoryType;
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
        self::assertSame([FileInterface::class => File::class, MediaCategoryInterface::class => MediaCategory::class], $doctrine['resolve_target_entities']);
        self::assertSame(['GingermindsMediaManagerFile', 'GingermindsMediaManagerMedia'], array_keys($doctrine['mappings']));
        self::assertSame('Gingerminds\MediaManagerBundle\Entity\File', $doctrine['mappings']['GingermindsMediaManagerFile']['prefix']);

        self::assertSame(
            ['local' => ['directory' => '%kernel.project_dir%/var/storage/media']],
            $this->merged($container, 'flysystem')['storages'][GingermindsMediaManagerBundle::DEFAULT_STORAGE],
        );
    }

    public function testTheMediaCategoriesAreACoreResource(): void
    {
        $resource = $this->merged($this->prepend([]), 'gingerminds_core')['resources']['media_category'];

        self::assertSame(MediaCategory::class, $resource['entity']);
        self::assertSame(MediaCategoryType::class, $resource['form']);
        self::assertSame('media-categories', $resource['path']);
        self::assertSame('media_categories', $resource['permission']);
        self::assertSame('gingerminds_media_manager_media_category', $resource['route_prefix']);
        self::assertSame('@GingermindsMediaManager/pages/media_category', $resource['template_prefix']);

        $resource = $this->merged($this->prepend([['resources' => ['media_category' => ['controller' => 'App\\Controller\\CategoryController']]]]), 'gingerminds_core')['resources']['media_category'];
        self::assertSame('App\\Controller\\CategoryController', $resource['controller']);
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
     * @return array<string, mixed>
     */
    private function merged(ContainerBuilder $container, string $extension): array
    {
        return array_replace_recursive([], ...$container->getExtensionConfig($extension));
    }
}
