<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\Api;

use Gingerminds\MediaManagerBundle\Tests\Functional\ApiTestCase;

final class MediaCategoryApiTest extends ApiTestCase
{
    public function testTheCategoriesArePublicAndOrderedByPosition(): void
    {
        $photos = $this->fixtures->mediaCategory('photos', position: 1);
        $this->fixtures->mediaCategory('videos', position: 0);
        $this->fixtures->mediaCategory('landscapes', $photos, 0);

        $data = $this->api('GET', '/api/media_categories');

        $this->assertStatus(200);
        self::assertSame(3, $data['totalItems']);
        self::assertSame(['videos', 'landscapes', 'photos'], array_column($data['member'], 'code'));
        $item = $data['member'][1];
        self::assertSame(['@id', '@type', 'id', 'code', 'name', 'position', 'parent_id'], array_keys($item));
        self::assertSame($photos->getId(), $item['parent_id']);
        self::assertNull($data['member'][0]['parent_id']);
    }

    public function testSortAndPageSize(): void
    {
        foreach (['charlie', 'alpha', 'bravo'] as $code) {
            $this->fixtures->mediaCategory($code);
        }

        $data = $this->api('GET', '/api/media_categories?itemsPerPage=2&sortBy=code&sort=desc');

        self::assertSame(['charlie', 'bravo'], array_column($data['member'], 'code'));
    }

    public function testAnItemHasItsChildren(): void
    {
        $photos = $this->fixtures->mediaCategory('photos');
        $this->fixtures->mediaCategory('portraits', $photos, 1);
        $this->fixtures->mediaCategory('landscapes', $photos, 0);
        $this->entityManager()->clear();

        $data = $this->api('GET', '/api/media_categories/' . $photos->getId());

        $this->assertStatus(200);
        self::assertNull($data['parent_id']);
        self::assertSame(['landscapes', 'portraits'], array_column($data['children'], 'code'));
    }

    public function testTheTreeNestsEveryLevel(): void
    {
        $photos = $this->fixtures->mediaCategory('photos', position: 1);
        $this->fixtures->mediaCategory('videos', position: 0);
        $landscapes = $this->fixtures->mediaCategory('landscapes', $photos, 1);
        $this->fixtures->mediaCategory('portraits', $photos, 0);
        $this->fixtures->mediaCategory('mountains', $landscapes);
        $this->entityManager()->clear();

        $data = $this->api('GET', '/api/media_categories/tree');

        $this->assertStatus(200);
        self::assertSame(['videos', 'photos'], array_column($data['member'], 'code'));
        self::assertSame(['@id', '@type', 'id', 'code', 'name', 'position', 'children', 'parent_id'], array_keys($data['member'][1]));
        self::assertNull($data['member'][1]['parent_id']);
        self::assertSame([], $data['member'][0]['children']);
        self::assertSame(['portraits', 'landscapes'], array_column($data['member'][1]['children'], 'code'));
        self::assertSame($photos->getId(), $data['member'][1]['children'][0]['parent_id']);
        self::assertSame(['mountains'], array_column($data['member'][1]['children'][1]['children'], 'code'));
    }
}
