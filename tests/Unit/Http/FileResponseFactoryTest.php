<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Unit\Http;

use Gingerminds\MediaManagerBundle\Entity\File\File;
use Gingerminds\MediaManagerBundle\Http\FileResponseFactory;
use Gingerminds\MediaManagerBundle\Http\FileResponseHeaders;
use Gingerminds\MediaManagerBundle\Storage\DiskRegistry;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FileResponseFactoryTest extends TestCase
{
    private Filesystem $filesystem;
    private FileResponseFactory $factory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->filesystem->write('library/a.txt', 'abc');
        $this->factory = new FileResponseFactory(new DiskRegistry(new ServiceLocator(['default' => fn (): Filesystem => $this->filesystem]), 'default'));
    }

    public function testTheCacheHeadersComeFromTheRow(): void
    {
        $file = $this->file('text/plain', 'a.txt', 'hash-of-a');
        $response = $this->factory->file(new Request(), $file);

        self::assertSame('"hash-of-a"', $response->getEtag());
        self::assertSame('3', $response->headers->get('Content-Length'));
        self::assertSame('text/plain', $response->headers->get('Content-Type'));

        $withoutHash = $this->factory->file(new Request(), $this->file('text/plain', 'a.txt'));
        self::assertNotNull($withoutHash->getEtag());
    }

    public function testANotModifiedResponseReadsNothing(): void
    {
        $file = $this->file('image/svg+xml', 'a.svg', 'hash-of-a', 'library/missing.svg');
        $response = $this->factory->file(new Request(server: ['HTTP_IF_NONE_MATCH' => '"hash-of-a"']), $file);

        self::assertSame(304, $response->getStatusCode());
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame(FileResponseHeaders::CONTENT_SECURITY_POLICY, $response->headers->get('Content-Security-Policy'));
    }

    public function testAPresetIsOnlyRenderedWhenTheBrowserDoesNotHaveIt(): void
    {
        $file = $this->file('image/png', 'a.png', 'hash-of-a');
        $rendered = 0;
        $render = function () use (&$rendered): string {
            ++$rendered;
            $this->filesystem->write('cache/a.png/rendered', 'webp');

            return 'cache/a.png/rendered';
        };

        $response = $this->factory->preset(new Request(), $file, 'thumbnail', ['w' => 50, 'fm' => 'webp'], $render);
        self::assertSame('image/webp', $response->headers->get('Content-Type'));
        self::assertSame(1, $rendered);

        $etag = (string) $response->getEtag();
        self::assertNotSame($etag, $this->factory->preset(new Request(), $file, 'thumbnail', ['w' => 60, 'fm' => 'webp'], $render)->getEtag(), 'Another definition.');
        self::assertSame(304, $this->factory->preset(new Request(server: ['HTTP_IF_NONE_MATCH' => $etag]), $file, 'thumbnail', ['w' => 50, 'fm' => 'webp'], $render)->getStatusCode());
        self::assertSame(2, $rendered);
    }

    #[DataProvider('asciiFallbacks')]
    public function testTheContentDispositionHasAnAsciiFallback(string $name, string $expected): void
    {
        self::assertSame($expected, $this->factory->file(new Request(), $this->file('text/plain', $name))->headers->get('Content-Disposition'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function asciiFallbacks(): iterable
    {
        yield 'transliterated' => ['Mon fichier été.txt', "inline; filename=\"Mon fichier ete.txt\"; filename*=utf-8''Mon%20fichier%20%C3%A9t%C3%A9.txt"];
        yield 'separators' => ['50%/a\\b.txt', "inline; filename=50__a_b.txt; filename*=utf-8''50%25_a_b.txt"];
        yield 'nothing left' => ['😀', "inline; filename=file; filename*=utf-8''%F0%9F%98%80"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function scriptableTypes(): iterable
    {
        yield 'svg' => ['image/svg+xml'];
        yield 'text' => ['text/plain'];
    }

    #[DataProvider('scriptableTypes')]
    public function testTheContentIsNeitherSniffedNorRunInTheOrigin(string $mimeType): void
    {
        $response = $this->factory->file(new Request(), $this->file($mimeType, 'a.txt'));

        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame(FileResponseHeaders::CONTENT_SECURITY_POLICY, $response->headers->get('Content-Security-Policy'));
    }

    public function testAPdfStaysViewableInTheBrowser(): void
    {
        $response = $this->factory->file(new Request(), $this->file('application/pdf', 'a.pdf'));

        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertNull($response->headers->get('Content-Security-Policy'));
    }

    public function testAMissingFileIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->factory->file(new Request(), $this->file('text/plain', 'missing.txt', null, 'library/missing.txt'));
    }

    private function file(string $mimeType, string $name, ?string $hash = null, string $path = 'library/a.txt'): File
    {
        return new File('default', $path, $mimeType, $name, 3, $hash);
    }
}
