<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\Api;

use Gingerminds\MediaManagerBundle\Tests\Functional\ApiTestCase;
use Gingerminds\MediaManagerBundle\Tests\Functional\FileFactory;

final class MediaApiTest extends ApiTestCase
{
    private FileFactory $files;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        $this->files = new FileFactory(self::getContainer()->get('test.file_storage'));
    }

    public function testTheMediasArePublicWithTheLaravelFields(): void
    {
        $photos = $this->fixtures->mediaCategory('photos');
        $file = $this->files->text('brochure.txt', 'brochure');
        $thumbnail = $this->files->png('cover.png');
        $media = $this->fixtures->media($file, 'Brochure', $photos, $thumbnail, 'brochure-2026');
        $this->fixtures->media($this->files->png('tractor.png'), 'Tractor');

        $data = $this->api('GET', '/api/media');

        $this->assertStatus(200);
        self::assertSame(2, $data['totalItems']);
        $item = $data['member'][0];
        self::assertSame([
            '@id', '@type', 'id', 'code', 'name', 'file', 'file_reference', 'file_size', 'file_type',
            'thumbnail_reference', 'thumbnail_size', 'media_category_id',
        ], array_keys($item));
        self::assertSame($media->getId(), $item['id']);
        self::assertSame('brochure-2026', $item['code']);
        self::assertSame($file->getId(), $item['file'], 'The id, never the path.');
        self::assertSame($file->getId(), $item['file_reference']);
        self::assertSame(8, $item['file_size']);
        self::assertSame('text/plain', $item['file_type']);
        self::assertSame($thumbnail->getId(), $item['thumbnail_reference']);
        self::assertSame($thumbnail->getSize(), $item['thumbnail_size']);
        self::assertSame($photos->getId(), $item['media_category_id']);
        self::assertNull($data['member'][1]['thumbnail_reference']);
        self::assertNull($data['member'][1]['media_category_id']);

        $data = $this->api('GET', '/api/media/' . $media->getId());
        $this->assertStatus(200);
        self::assertSame('Brochure', $data['name']);
    }

    public function testFilterByCategory(): void
    {
        $photos = $this->fixtures->mediaCategory('photos');
        $videos = $this->fixtures->mediaCategory('videos');
        $this->fixtures->media($this->files->png('tractor.png'), 'Tractor', $photos);
        $this->fixtures->media($this->files->text('clip.txt', 'clip'), 'Clip', $videos);
        $this->fixtures->media($this->files->text('notes.txt', 'notes'), 'Notes');

        self::assertSame(['Tractor'], $this->names('/api/media?filters[category]=' . $photos->getId()));
        self::assertSame(['Clip'], $this->names('/api/media?filters[media_category_id][]=' . $videos->getId()), 'Laravel filter name.');
        self::assertSame(['Notes'], $this->names('/api/media?filters[media_category_id]=null'));
        self::assertSame(['Tractor'], $this->names('/api/media?filters[search]=tract'));
    }

    public function testAChangeIsSeenByTheNextRequest(): void
    {
        $media = $this->fixtures->media($this->files->png('tractor.png'), 'Tractor');

        self::assertSame(['Tractor'], $this->names('/api/media'));

        $media->setName('Red tractor');
        $this->entityManager()->flush();

        self::assertSame(['Red tractor'], $this->names('/api/media'));
        self::assertSame('Red tractor', $this->api('GET', '/api/media/' . $media->getId())['name']);
    }

    /**
     * @return list<string>
     */
    private function names(string $uri): array
    {
        $data = $this->api('GET', $uri);
        $this->assertStatus(200);

        return array_column($data['member'], 'name');
    }
}
