<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Unit\Http;

use Gingerminds\MediaManagerBundle\Http\FileResponseFactory;
use Gingerminds\MediaManagerBundle\Storage\DiskRegistry;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
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
