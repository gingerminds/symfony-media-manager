<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\Admin;

use Gingerminds\MediaManagerBundle\Tests\Functional\ApiTestCase;
use Gingerminds\MediaManagerBundle\Tests\Functional\FileFactory;
use Symfony\Component\DomCrawler\Crawler;

final class MediaPickerTest extends ApiTestCase
{
    private FileFactory $files;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        $this->files = new FileFactory(self::getContainer()->get('test.file_storage'));
    }

    public function testTheModalHasTheCategoryTree(): void
    {
        $photos = $this->fixtures->mediaCategory('photos');
        $landscapes = $this->fixtures->mediaCategory('landscapes', $photos);
        $mountains = $this->fixtures->mediaCategory('mountains', $landscapes);
        $this->entityManager()->clear();
        $this->client->loginUser($this->fixtures->user('viewer@example.com', ['view medias']), 'admin');

        $crawler = $this->client->request('GET', '/admin/medias/picker');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-controller="gm-media-picker"]'));
        self::assertSame(
            [['', ''], [(string) $photos->getId(), ''], [(string) $landscapes->getId(), (string) $photos->getId()], [(string) $mountains->getId(), $landscapes->getId() . ' ' . $photos->getId()]],
            $crawler->filter('select option')->each(static fn (Crawler $option): array => [(string) $option->attr('value'), (string) $option->attr('data-ancestors')]),
        );
        self::assertSame('— — Mountains', $crawler->filter('select option')->last()->text());
    }

    public function testSearch(): void
    {
        $photos = $this->fixtures->mediaCategory('photos');
        $landscapes = $this->fixtures->mediaCategory('landscapes', $photos);
        $videos = $this->fixtures->mediaCategory('videos');
        $this->fixtures->media($this->files->png('tractor.png'), 'Tractor', $photos, code: 'tractor-red');
        $this->fixtures->media($this->files->png('alps.png', 30, 30), 'Alps', $landscapes);
        $this->fixtures->media($this->files->text('clip.txt', 'clip'), 'Clip', $videos);
        $this->client->loginUser($this->fixtures->user('viewer@example.com', ['view medias']), 'admin');

        $data = $this->search('');
        self::assertSame(['Alps', 'Clip', 'Tractor'], array_column($data['items'], 'name'));
        self::assertSame(['id', 'code', 'name', 'category', 'file', 'thumbnailUrl'], array_keys($data['items'][0]));
        self::assertSame('Landscapes', $data['items'][0]['category']);
        self::assertNull($data['items'][1]['thumbnailUrl'], 'Neither an image nor a thumbnail.');

        self::assertSame(['Tractor'], array_column($this->search('search=red')['items'], 'name'), 'By code.');
        self::assertSame(['Alps', 'Tractor'], array_column($this->search('category=' . $photos->getId())['items'], 'name'), 'With the subcategories.');
        self::assertSame(['Alps', 'Tractor'], array_column($this->search('categories[]=' . $photos->getId())['items'], 'name'), 'Allowed categories.');
        self::assertSame([], $this->search('categories[]=' . $photos->getId() . '&category=' . $videos->getId())['items'], 'Outside the allowed categories.');

        $data = $this->search('per_page=2');
        self::assertSame(['Alps', 'Clip'], array_column($data['items'], 'name'));
        self::assertTrue($data['hasMore']);
        self::assertSame(3, $data['total']);
        self::assertFalse($this->search('per_page=2&page=2')['hasMore']);
    }

    public function testThePickerNeedsThePermission(): void
    {
        $this->client->loginUser($this->fixtures->user('nobody@example.com', ['view files']), 'admin');

        $this->client->request('GET', '/admin/medias/picker');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/medias/search');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function search(string $query): array
    {
        $this->client->request('GET', '/admin/medias/search?' . $query, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
