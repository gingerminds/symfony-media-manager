<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\AssetMapper;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;

final class RelativeImportCompilerTest extends KernelTestCase
{
    public function testTheBundleScriptsImportTheVersionedModules(): void
    {
        $assetMapper = self::getContainer()->get(AssetMapperInterface::class);
        $controller = $assetMapper->getAsset('gingerminds-media-manager/controllers/file_browser_controller.js');
        $helpers = $assetMapper->getAsset('gingerminds-media-manager/file_browser/helpers.js');

        self::assertInstanceOf(MappedAsset::class, $controller);
        self::assertInstanceOf(MappedAsset::class, $helpers);
        self::assertStringContainsString("import helpers from '" . $helpers->publicPath . "';", (string) $controller->content);
        self::assertStringNotContainsString("'../file_browser/", (string) $controller->content);
        self::assertContains($helpers->logicalPath, array_map(static fn (MappedAsset $asset): string => $asset->logicalPath, $controller->getDependencies()));
    }

    public function testTheOtherScriptsAreLeftToTheImportmap(): void
    {
        $admin = self::getContainer()->get(AssetMapperInterface::class)->getAsset('gingerminds-core/admin.js');

        self::assertInstanceOf(MappedAsset::class, $admin);
        // Content left null when no compiler changed it.
        self::assertStringContainsString("from './controllers/", $admin->content ?? (string) file_get_contents($admin->sourcePath));
    }
}
