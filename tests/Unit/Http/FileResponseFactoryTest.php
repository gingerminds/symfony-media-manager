<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Unit\Http;

use Gingerminds\MediaManagerBundle\Http\FileResponseFactory;
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

    public function testTheEtagMatchesTheLaravelOne(): void
    {
        $response = $this->factory->create(new Request(), 'default', 'library/a.txt', 'text/plain');

        self::assertSame('"' . md5('library/a.txt' . $this->filesystem->lastModified('library/a.txt')) . '"', $response->getEtag());
        self::assertNull($response->headers->get('Content-Disposition'));
    }

    #[DataProvider('asciiFallbacks')]
    public function testTheContentDispositionHasAnAsciiFallback(string $name, string $expected): void
    {
        $response = $this->factory->create(new Request(), 'default', 'library/a.txt', 'text/plain', $name);

        self::assertSame($expected, $response->headers->get('Content-Disposition'));
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

    public function testNotModifiedSinceLastModified(): void
    {
        $request = new Request(server: ['HTTP_IF_MODIFIED_SINCE' => gmdate('D, d M Y H:i:s', time() + 60) . ' GMT']);

        self::assertSame(304, $this->factory->create($request, 'default', 'library/a.txt')->getStatusCode());
    }

    public function testAMissingFileIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->factory->create(new Request(), 'default', 'library/missing.txt');
    }
}
