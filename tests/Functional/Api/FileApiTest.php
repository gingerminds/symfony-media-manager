<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\Api;

use Gingerminds\MediaManagerBundle\Tests\Functional\ApiTestCase;
use Gingerminds\MediaManagerBundle\Tests\Functional\FileFactory;
use League\Flysystem\FilesystemOperator;

final class FileApiTest extends ApiTestCase
{
    private FileFactory $files;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        $this->files = new FileFactory(self::getContainer()->get('test.file_storage'));
    }

    public function testTheOriginalFileIsServedWithCacheHeaders(): void
    {
        $file = $this->files->text('Mon fichier été.txt', 'hello');

        $this->client->request('GET', '/api/files/' . $file->getId());

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertSame('hello', $this->client->getInternalResponse()->getContent());
        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('5', $response->headers->get('Content-Length'));
        self::assertStringStartsWith('inline; filename="Mon fichier ete.txt"; filename*=utf-8\'\'Mon%20fichier%20%C3%A9t%C3%A9.txt', (string) $response->headers->get('Content-Disposition'));
        self::assertSame('max-age=86400, must-revalidate, public', $response->headers->get('Cache-Control'));
        self::assertNotNull($response->headers->get('Last-Modified'));

        $this->client->request('GET', '/api/files/' . $file->getId(), server: ['HTTP_IF_NONE_MATCH' => (string) $response->headers->get('ETag')]);

        self::assertResponseStatusCodeSame(304);
    }

    public function testAnUnknownFileIsNotFound(): void
    {
        $this->client->request('GET', '/api/files/0199b0c4-7a7c-7c5e-9d3e-5b8f8a6c1d2e');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/api/files/not-a-uuid');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAMissingPhysicalFileIsNotFound(): void
    {
        $file = $this->files->text();
        /** @var FilesystemOperator $storage */
        $storage = self::getContainer()->get('test.storage.default');
        $storage->delete($file->getPath());

        $this->client->request('GET', '/api/files/' . $file->getId());

        self::assertResponseStatusCodeSame(404);
    }

    public function testAPresetRendersTheImage(): void
    {
        $file = $this->files->png();

        $this->client->request('GET', '/api/files/' . $file->getId() . '/thumbnail');

        self::assertResponseIsSuccessful();
        self::assertSame('image/webp', $this->client->getResponse()->headers->get('Content-Type'));
        $size = getimagesizefromstring($this->client->getInternalResponse()->getContent());
        self::assertIsArray($size);
        self::assertSame([50, 40], [$size[0], $size[1]]);
    }

    public function testThePresetFormatOverridesTheDefaultOne(): void
    {
        $file = $this->files->png();

        $this->client->request('GET', '/api/files/' . $file->getId() . '/card');

        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testAnSvgIsServedAsIs(): void
    {
        $file = $this->files->svg();

        $this->client->request('GET', '/api/files/' . $file->getId() . '/thumbnail');

        self::assertResponseIsSuccessful();
        self::assertSame('image/svg+xml', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('<svg', $this->client->getInternalResponse()->getContent());
    }

    public function testAPresetNeedsAnImageAndAKnownPreset(): void
    {
        $this->client->request('GET', '/api/files/' . $this->files->text()->getId() . '/thumbnail');
        self::assertResponseStatusCodeSame(400);

        $this->client->request('GET', '/api/files/' . $this->files->png()->getId() . '/huge');
        self::assertResponseStatusCodeSame(404);
    }

    public function testRequestsAreRateLimited(): void
    {
        $file = $this->files->text();

        for ($i = 0; $i < 5; ++$i) {
            $this->client->request('GET', '/api/files/' . $file->getId());
            self::assertResponseIsSuccessful();
        }

        $this->client->request('GET', '/api/files/' . $file->getId());

        self::assertResponseStatusCodeSame(429);
        self::assertSame('5', $this->client->getResponse()->headers->get('X-RateLimit-Limit'));
    }

    public function testThePresetsAreDocumented(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');

        self::assertResponseIsSuccessful();
        $docs = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $parameters = array_column($docs['paths']['/api/files/{id}/{preset}']['get']['parameters'], null, 'name');
        self::assertSame(['thumbnail', 'card'], $parameters['preset']['schema']['enum']);
    }

    public function testTwigBuildsTheFileUrls(): void
    {
        $file = $this->files->png();
        $twig = self::getContainer()->get('twig')->createTemplate("{{ gm_file_url(file) }}|{{ gm_file_url(file, 'card') }}|{{ gm_file_url(null) }}");

        self::assertSame('/api/files/' . $file->getId() . '|/api/files/' . $file->getId() . '/card|', $twig->render(['file' => $file]));
    }
}
