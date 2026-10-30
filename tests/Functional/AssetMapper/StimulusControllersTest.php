<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\AssetMapper;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\ImportMap\JavaScriptImport;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\UX\StimulusBundle\AssetMapper\ControllersMapGenerator;

/**
 * The bundle is a UX package: its controllers come from the controllers.json of the test
 * application (what Flex writes in a project) and assets/package.json.
 */
final class StimulusControllersTest extends KernelTestCase
{
    public function testTheControllersKeepTheirGmNames(): void
    {
        $generator = self::getContainer()->get('stimulus.asset_mapper.controllers_map_generator');
        self::assertInstanceOf(ControllersMapGenerator::class, $generator);
        $controllers = $generator->getControllersMap();

        foreach (['gm-file-upload', 'gm-file-pick', 'gm-file-picker', 'gm-file-dialog', 'gm-file-filters', 'gm-file-selection', 'gm-media-picker', 'gm-media-select'] as $name) {
            self::assertArrayHasKey($name, $controllers);
            self::assertTrue($controllers[$name]->isLazy, $name);
        }

        self::assertSame('gingerminds-media-manager/controllers/file_picker_controller.js', $controllers['gm-file-picker']->asset->logicalPath);
    }

    public function testTheRelativeImportsAreResolvedByTheImportmap(): void
    {
        $picker = self::getContainer()->get(AssetMapperInterface::class)->getAsset('gingerminds-media-manager/controllers/file_picker_controller.js');

        self::assertInstanceOf(MappedAsset::class, $picker);
        self::assertContains('gingerminds-media-manager/lib/helpers.js', array_map(static fn (JavaScriptImport $import): string => $import->assetLogicalPath, $picker->getJavaScriptImports()));
    }
}
