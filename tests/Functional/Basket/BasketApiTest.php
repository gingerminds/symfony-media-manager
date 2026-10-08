<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\Basket;

use Gingerminds\MediaManagerBundle\Basket\Entity\Basket;
use Gingerminds\MediaManagerBundle\Entity\Media\Media;
use Gingerminds\MediaManagerBundle\Media\MediaUsageCounter;
use Gingerminds\MediaManagerBundle\Tests\Functional\ApiTestCase;
use Gingerminds\MediaManagerBundle\Tests\Functional\FileFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class BasketApiTest extends ApiTestCase
{
    private FileFactory $files;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        $this->files = new FileFactory(self::getContainer()->get('test.file_storage'));
    }

    public function testAGuestBasket(): void
    {
        [$first, $second] = $this->medias(2);

        $basket = $this->api('POST', '/api/baskets', json: []);
        $this->assertStatus(201);
        self::assertSame(['@context', '@id', '@type', 'token', 'expires_at', 'medias'], array_keys($basket));
        self::assertEqualsWithDelta(new \DateTimeImmutable('+30 days'), new \DateTimeImmutable($basket['expires_at']), 60);
        $url = '/api/baskets/' . $basket['token'];

        $basket = $this->api('POST', $url . '/medias', json: ['media_ids' => [$first->getId(), (string) $second->getId(), $first->getId()]]);
        $this->assertStatus(200);
        self::assertSame([$first->getId(), $second->getId()], array_column($basket['medias'], 'id'));
        self::assertSame(['@id', '@type', 'id', 'code', 'name', 'file', 'file_reference', 'file_size', 'file_type', 'thumbnail_reference', 'thumbnail_size', 'media_category_id'], array_keys($basket['medias'][0]));

        $basket = $this->api('DELETE', $url . '/medias/' . $first->getId());
        $this->assertStatus(200);
        self::assertSame([$second->getId()], array_column($basket['medias'], 'id'));
        self::assertSame([$second->getId()], array_column($this->api('GET', $url)['medias'], 'id'));

        $this->api('POST', $url . '/medias', json: ['media_ids' => ['one']]);
        $this->assertStatus(422);
        $this->api('POST', $url . '/medias', json: ['media_ids' => [999999]]);
        $this->assertStatus(422);

        $this->api('DELETE', $url);
        $this->assertStatus(204);
        $this->api('GET', $url);
        $this->assertStatus(404);
    }

    public function testTheBasketOfAUser(): void
    {
        $owner = $this->fixtures->token($this->fixtures->user('owner@example.com'));
        $other = $this->fixtures->token($this->fixtures->user('other@example.com'));

        $previous = $this->api('POST', '/api/baskets', $owner, json: []);
        $basket = $this->api('POST', '/api/baskets', $owner, json: []);
        $this->assertStatus(201);
        self::assertNull($basket['expires_at'], 'A user basket never expires.');
        $url = '/api/baskets/' . $basket['token'];

        $this->api('GET', '/api/baskets/' . $previous['token'], $owner);
        $this->assertStatus(404);
        $this->api('GET', $url, $owner);
        $this->assertStatus(200);
        $this->api('GET', $url, $other);
        $this->assertStatus(403);
        $this->api('GET', $url);
        $this->assertStatus(403);
        $this->api('DELETE', $url, $other);
        $this->assertStatus(403);
    }

    public function testTheGuestBasketIsMergedAtLogin(): void
    {
        [$first, $second] = $this->medias(2);
        $this->fixtures->user('claim@example.com');

        $login = $this->api('POST', '/api/login', json: ['email' => 'claim@example.com', 'password' => 'password123']);
        $this->assertStatus(200);
        $userBasket = $this->api('GET', '/api/baskets/' . $login['basket_token'], $login['token']);
        $this->assertStatus(200);
        self::assertSame($login['basket_token'], $this->api('POST', '/api/login', json: ['email' => 'claim@example.com', 'password' => 'password123'])['basket_token'], 'Same basket.');

        $guest = $this->api('POST', '/api/baskets', json: []);
        $this->api('POST', '/api/baskets/' . $guest['token'] . '/medias', json: ['media_ids' => [$first->getId(), $second->getId()]]);

        $basket = $this->api('POST', '/api/baskets', $login['token'], json: ['anonymous_token' => $guest['token']]);
        $this->assertStatus(201);
        self::assertSame([$first->getId(), $second->getId()], array_column($basket['medias'], 'id'), 'Merged (claim_strategy: merge).');
        self::assertNotSame($userBasket['token'], $basket['token']);
        $this->api('GET', '/api/baskets/' . $guest['token']);
        $this->assertStatus(404);
    }

    public function testClaimStrategies(): void
    {
        [$guestMedia, $userMedia] = $this->medias(2);
        $repository = $this->entityManager()->getRepository(Basket::class);
        $results = [];

        foreach (['merge', 'replace', 'ignore'] as $strategy) {
            $guest = new Basket();
            $guest->addMedia($guestMedia);
            $basket = new Basket();
            $basket->addMedia($userMedia);
            $this->entityManager()->persist($guest);
            $this->entityManager()->persist($basket);
            $this->entityManager()->flush();

            $repository->claim($guest, $basket, $strategy);
            $results[$strategy] = array_map(static fn (Media $media): ?int => $media->getId(), $basket->getMedias());
        }

        self::assertSame(['merge' => [$userMedia->getId(), $guestMedia->getId()], 'replace' => [$guestMedia->getId()], 'ignore' => [$userMedia->getId()]], $results);
    }

    public function testDownload(): void
    {
        $photo = $this->fixtures->media($this->files->png('photo.png'), 'Photo');
        $copy = $this->fixtures->media($this->files->png('photo.png', 30, 30), 'Copy');
        $notes = $this->fixtures->media($this->files->text('notes.txt', 'notes'), 'Notes');
        $basket = $this->api('POST', '/api/baskets', json: []);
        $url = '/api/baskets/' . $basket['token'];

        $this->client->request('GET', $url . '/download');
        self::assertResponseStatusCodeSame(422, 'Empty basket.');

        $this->api('POST', $url . '/medias', json: ['media_ids' => [$photo->getId(), $copy->getId(), $notes->getId()]]);
        $this->client->request('GET', $url . '/download');

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame('attachment; filename=basket.zip', $response->headers->get('Content-Disposition'));
        // The file is deleted once sent: the content is in the internal response.
        $path = (string) tempnam(sys_get_temp_dir(), 'gm-test-zip');
        file_put_contents($path, $this->client->getInternalResponse()->getContent());
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path));
        self::assertSame(['photo.png', 'photo (2).png', 'notes.txt'], array_map(static fn (int $index): string => (string) $zip->getNameIndex($index), range(0, $zip->numFiles - 1)));
        self::assertSame('notes', $zip->getFromName('notes.txt'));
        $zip->close();
        unlink($path);
        self::assertFileDoesNotExist($response->getFile()->getPathname());

        $this->api('GET', $url);
        $this->assertStatus(404);
    }

    public function testAnExpiredGuestBasketIsGoneAndPurged(): void
    {
        $basket = $this->api('POST', '/api/baskets', json: []);
        $expired = $this->entityManager()->getRepository(Basket::class)->findOneBy(['token' => $basket['token']]);
        self::assertInstanceOf(Basket::class, $expired);
        $expired->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->entityManager()->flush();
        $this->api('POST', '/api/baskets', $this->fixtures->token($this->fixtures->user('kept@example.com')), json: []);

        $this->api('GET', '/api/baskets/' . $basket['token']);
        $this->assertStatus(404);

        $tester = new CommandTester(new Application(self::$kernel)->find('gingerminds:media:basket:purge'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('1 expired basket(s) deleted', $tester->getDisplay());
        self::assertSame(1, $this->entityManager()->getRepository(Basket::class)->count(), 'The user basket is kept.');
    }

    public function testAMediaInABasketCanBeDeleted(): void
    {
        [$media] = $this->medias(1);
        $basket = new Basket();
        $basket->addMedia($media);
        $this->entityManager()->persist($basket);
        $this->entityManager()->flush();

        self::assertSame(0, self::getContainer()->get(MediaUsageCounter::class)->count($media));
    }

    /**
     * @return list<Media>
     */
    private function medias(int $count): array
    {
        return array_map(fn (int $index): Media => $this->fixtures->media($this->files->png('media-' . $index . '.png', 10 + $index, 10), 'Media ' . $index), range(1, $count));
    }
}
