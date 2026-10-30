<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Unit\Image;

use Gingerminds\MediaManagerBundle\Entity\File\File;
use Gingerminds\MediaManagerBundle\Image\ImageProcessor;
use Gingerminds\MediaManagerBundle\Storage\DiskRegistry;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class ImageProcessorTest extends TestCase
{
    private ImageProcessor $images;

    protected function setUp(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('library/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->images = new ImageProcessor(new DiskRegistry(new ServiceLocator(['default' => static fn (): Filesystem => $filesystem]), 'default'), 'gd', 'png', '.cache', ['thumbnail' => ['w' => 10]]);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function mimeTypes(): iterable
    {
        yield 'png' => ['image/png', true];
        yield 'svg' => ['image/svg+xml', false];
        yield 'svg of older libmagic' => ['image/svg', false];
        yield 'not an image' => ['application/pdf', false];
    }

    #[DataProvider('mimeTypes')]
    public function testOnlyRasterImagesAreSupported(string $mimeType, bool $supported): void
    {
        self::assertSame($supported, $this->images->supports(new File('default', 'library/a', $mimeType, 'a', 1)));
    }

    public function testGlideNeverRendersAnSvg(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->images->process(new File('default', 'library/logo.svg', 'image/svg+xml', 'logo.svg', 1), 'thumbnail');
    }
}
