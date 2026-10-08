<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Unit\DependencyInjection;

use Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = $this->process([]);

        self::assertSame('default', $config['storage']['default_disk']);
        self::assertSame(['default' => 'gingerminds_media_manager.storage.default'], $config['storage']['disks']);
        self::assertSame('library', $config['library']['root']);
        self::assertSame(51200, $config['library']['max_upload_size']);
        self::assertSame(1000, $config['library']['max_directory_move']);
        self::assertContains('application/xlsx', $config['library']['allowed_mimes']);
        self::assertSame('imagick', $config['images']['driver']);
        self::assertSame(['micro', 'thumbnail', 'card', 'hero'], array_keys($config['images']['presets']));
        self::assertSame(['w' => 150, 'h' => 150, 'fit' => 'crop', 'q' => 80], $config['images']['presets']['thumbnail']);
        self::assertSame(600, $config['files_rate_limit']);
        self::assertSame(['enabled' => true, 'claim_strategy' => 'merge', 'ttl' => 30], $config['basket']);
        self::assertSame(['entity' => null, 'controller' => null, 'form' => null], $config['resources']['media']);
    }

    public function testCustomPresetsReplaceTheDefaultsAndKeepExtraGlideParameters(): void
    {
        $config = $this->process(['images' => ['presets' => ['banner' => ['w' => 1920, 'fm' => 'jpg', 'blur' => 5]]]]);

        self::assertSame(['banner' => ['w' => 1920, 'fm' => 'jpg', 'blur' => 5]], $config['images']['presets']);
    }

    public function testLaravelDiskNamesCanBeDeclared(): void
    {
        $config = $this->process(['storage' => ['default_disk' => 'public', 'disks' => ['public' => 'app.storage.public']]]);

        self::assertSame(['public' => 'app.storage.public'], $config['storage']['disks']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'preset name not URL safe' => [['images' => ['presets' => ['Big Hero' => ['w' => 100]]]]];
        yield 'unknown image format' => [['images' => ['default_format' => 'bmp']]];
        yield 'unknown claim strategy' => [['basket' => ['claim_strategy' => 'keep']]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationIsRejected(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process($config);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        $container = new ContainerBuilder();
        $configuration = new GingermindsMediaManagerBundle()->getContainerExtension()?->getConfiguration([], $container);
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        return new Processor()->processConfiguration($configuration, [$config]);
    }
}
