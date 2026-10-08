<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Image;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Exception\UnknownImagePresetException;
use Gingerminds\MediaManagerBundle\Storage\DiskRegistry;
use League\Glide\Server;
use League\Glide\ServerFactory;

/**
 * Glide presets, rendered and cached on the disk of each file.
 */
class ImageProcessor
{
    /**
     * @var array<string, Server>
     */
    private array $servers = [];

    /**
     * @param array<string, array<string, mixed>> $presets
     */
    public function __construct(
        private readonly DiskRegistry $disks,
        private readonly string $driver,
        private readonly string $defaultFormat,
        private readonly string $cachePrefix,
        private readonly array $presets,
    ) {
    }

    /**
     * @return list<string>
     */
    public function presets(): array
    {
        return array_map(strval(...), array_keys($this->presets));
    }

    public function hasPreset(string $preset): bool
    {
        return isset($this->presets[$preset]);
    }

    /**
     * Raster images only: Glide cannot render SVG.
     */
    public function supports(FileInterface $file): bool
    {
        return $file->isImage() && 'image/svg+xml' !== $file->getMimeType();
    }

    /**
     * Path of the rendered image, on the disk of the file.
     */
    public function process(FileInterface $file, string $preset): string
    {
        if (!$this->hasPreset($preset)) {
            throw UnknownImagePresetException::named($preset);
        }

        return $this->server($file->getDisk())->makeImage($file->getPath(), ['p' => $preset]);
    }

    public function clear(FileInterface $file): void
    {
        $this->server($file->getDisk())->deleteCache($file->getPath());
    }

    public function clearAll(string $disk): void
    {
        $this->disks->get($disk)->deleteDirectory($this->cachePrefix);
    }

    private function server(string $disk): Server
    {
        $filesystem = $this->disks->get($disk);

        return $this->servers[$disk] ??= ServerFactory::create([
            'source' => $filesystem,
            'cache' => $filesystem,
            'cache_path_prefix' => $this->cachePrefix,
            'driver' => $this->driver,
            'presets' => $this->presets,
            'defaults' => ['fm' => $this->defaultFormat],
        ]);
    }
}
