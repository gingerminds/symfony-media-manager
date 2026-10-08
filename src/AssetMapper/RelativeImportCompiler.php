<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\AssetMapper;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\AssetMapper\Exception\CircularAssetsException;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Filesystem\Path;

/**
 * Relative imports of the bundle scripts become their versioned public path: the scripts are
 * loaded by URL from the admin head, outside the project importmap that maps them otherwise.
 */
final readonly class RelativeImportCompiler implements AssetCompilerInterface
{
    private const string IMPORT_PATTERN = '/(\bfrom\s*|\bimport\s*\(?\s*)([\'"])(\.\.?\/[^\'"]+)\2/';

    public function __construct(
        private string $logicalPathPrefix,
    ) {
    }

    public function supports(MappedAsset $asset): bool
    {
        return 'js' === $asset->publicExtension && str_starts_with($asset->logicalPath, $this->logicalPathPrefix . '/');
    }

    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
    {
        return (string) preg_replace_callback(self::IMPORT_PATTERN, static function (array $matches) use ($asset, $assetMapper): string {
            try {
                $dependency = $assetMapper->getAssetFromSourcePath(Path::join(\dirname($asset->sourcePath), $matches[3]));
            } catch (CircularAssetsException $exception) {
                $dependency = $exception->getIncompleteMappedAsset();
            }

            if (!$dependency instanceof MappedAsset) {
                return $matches[0];
            }

            $asset->addDependency($dependency);

            return $matches[1] . $matches[2] . $dependency->publicPath . $matches[2];
        }, $content);
    }
}
