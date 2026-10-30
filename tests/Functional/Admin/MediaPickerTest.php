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
        $videos = $this->fixtures->mediaCategory('videos');
        $this->entityManager()->clear();
        $this->client->loginUser($this->fixtures->user('viewer@example.com', ['view medias']), 'admin');

        $crawler = $this->client->request('GET', '/admin/medias/picker');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#gm-media-picker-modal[data-controller="gm-pick"] turbo-frame#gm-media-picker'));
        self::assertSame(['', (string) $photos->getId(), (string) $landscapes->getId(), (string) $mountains->getId(), (string) $videos->getId()], $this->options($crawler));
        self::assertSame('— — Mountains', $crawler->filter('select[name="category"] option')->eq(3)->text());

        $allowed = $this->client->request('GET', '/admin/medias/picker?categories[]=' . $landscapes->getId() . '&categories[]=' . $videos->getId());
        self::assertSame(['', (string) $landscapes->getId(), (string) $mountains->getId(), (string) $videos->getId()], $this->options($allowed), 'The allowed ones and their subcategories.');
        self::assertCount(0, $this->client->request('GET', '/admin/medias/picker?categories[]=' . $photos->getId())->filter('select[name="category"]'), 'One allowed category locks the filter.');
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

        $crawler = $this->picker('');
        self::assertSame(['Alps', 'Clip', 'Tractor'], $this->names($crawler));
        $item = json_decode((string) $crawler->filter('[data-gm-pick-item-param]')->first()->attr('data-gm-pick-item-param'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['id', 'code', 'name', 'category', 'file', 'thumbnailUrl'], array_keys($item));
        self::assertSame('Landscapes', $item['category']);
        $clip = $crawler->filter('[data-pick-id]')->reduce(static fn (Crawler $card): bool => str_contains($card->text(), 'Clip'));
        self::assertCount(0, $clip->filter('img'), 'Neither an image nor a thumbnail.');

        self::assertSame(['Tractor'], $this->names($this->picker('search=red')), 'By code.');
        self::assertSame(['Alps', 'Tractor'], $this->names($this->picker('category=' . $photos->getId())), 'With the subcategories.');
        self::assertSame(['Alps', 'Tractor'], $this->names($this->picker('categories[]=' . $photos->getId())), 'Allowed categories.');
        self::assertSame([], $this->names($this->picker('categories[]=' . $photos->getId() . '&category=' . $videos->getId())), 'Outside the allowed categories.');

        $page = $this->picker('per_page=2');
        self::assertSame(['Alps', 'Clip'], $this->names($page));
        self::assertSame(['Tractor'], $this->names($this->picker('per_page=2&page=2')));
        self::assertNotCount(0, $page->filter('turbo-frame#gm-media-picker .pagination a[href*="page=2"][href*="per_page=2"]'), 'The pages keep the state.');
    }

    public function testThePickerNeedsThePermission(): void
    {
        $this->client->loginUser($this->fixtures->user('nobody@example.com', ['view files']), 'admin');

        $this->client->request('GET', '/admin/medias/picker');
        self::assertResponseStatusCodeSame(403);
    }

    private function picker(string $query): Crawler
    {
        $crawler = $this->client->request('GET', '/admin/medias/picker?' . $query);
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /**
     * @return list<string>
     */
    private function names(Crawler $crawler): array
    {
        return $crawler->filter('[data-pick-id] .gm-file-card-name')->each(static fn (Crawler $name): string => $name->text());
    }

    /**
     * @return list<string>
     */
    private function options(Crawler $crawler): array
    {
        return $crawler->filter('select[name="category"] option')->each(static fn (Crawler $option): string => (string) $option->attr('value'));
    }
}
