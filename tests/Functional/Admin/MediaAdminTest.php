<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\Admin;

use Gingerminds\MediaManagerBundle\Entity\File\File;
use Gingerminds\MediaManagerBundle\Entity\Media\Media;
use Gingerminds\MediaManagerBundle\Tests\Functional\ApiTestCase;
use Gingerminds\MediaManagerBundle\Tests\Functional\FileFactory;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MediaAdminTest extends ApiTestCase
{
    private FileFactory $files;

    protected function setUp(): void
    {
        parent::setUp();
        // Same container for every request: the files live in an in-memory storage.
        $this->client->disableReboot();
        $this->files = new FileFactory(self::getContainer()->get('test.file_storage'));
    }

    public function testTheListShowsThePreviewAndFilters(): void
    {
        $photos = $this->fixtures->mediaCategory('photos');
        $photo = $this->fixtures->media($this->files->png('tractor.png'), 'Tractor', $photos);
        $thumbnail = $this->files->png('cover.png', 20, 20);
        $this->fixtures->media($this->files->text('brochure.txt', 'brochure'), 'Brochure', thumbnail: $thumbnail);
        $this->fixtures->media($this->files->text('notes.txt', 'notes'), 'Notes');
        $this->client->loginUser($this->fixtures->user('viewer@example.com', ['view medias']), 'admin');

        $crawler = $this->client->request('GET', '/admin/medias?sortBy=name');

        self::assertResponseIsSuccessful();
        self::assertSame(['Brochure', 'Notes', 'Tractor'], $this->names($crawler));
        self::assertSame(
            ['/api/files/' . $thumbnail->getId() . '/thumbnail', '/api/files/' . $photo->getFile()?->getId() . '/thumbnail'],
            $crawler->filter('img.gm-media-preview')->each(static fn (Crawler $img): string => (string) $img->attr('src')),
        );
        self::assertCount(0, $crawler->filter('a[href="/admin/medias/new"]'), 'No create button for a viewer.');
        self::assertCount(1, $crawler->filter('#gm-sidebar a[href="/admin/medias"]'));

        $crawler = $this->client->request('GET', '/admin/medias?filters[category][]=' . $photos->getId());
        self::assertSame(['Tractor'], $this->names($crawler));

        $crawler = $this->client->request('GET', '/admin/medias?filters[search]=broch');
        self::assertSame(['Brochure'], $this->names($crawler));
    }

    public function testCreateWithTheFilePicker(): void
    {
        $photos = $this->fixtures->mediaCategory('photos');
        $file = $this->files->text('brochure.txt', 'brochure');
        $thumbnail = $this->files->png('cover.png');
        $this->client->loginUser($this->fixtures->user('editor@example.com', ['view medias', 'edit medias', 'view files']), 'admin');

        $crawler = $this->client->request('GET', '/admin/medias/new?category_id=' . $photos->getId());
        self::assertResponseIsSuccessful();
        self::assertSame((string) $photos->getId(), $crawler->filter('#media_category option[selected]')->attr('value'));
        self::assertCount(2, $crawler->filter('[data-controller="gm-file-picker"]'));
        self::assertSame(['image/*'], json_decode((string) $crawler->filter('#media_thumbnail')->closest('[data-controller="gm-file-picker"]')?->attr('data-gm-file-picker-accept-value'), true));

        $form = $crawler->filter('form[name="media"]')->form();
        $form['media[code]'] = '  brochure-2026 ';
        $form['media[file]'] = $file->getId();
        $form['media[thumbnail]'] = $thumbnail->getId();
        $this->client->submit($form);

        self::assertResponseRedirects();
        $media = $this->media();
        self::assertSame('brochure.txt', $media->getName(), 'Named after its file.');
        self::assertSame('brochure-2026', $media->getCode());
        self::assertSame($file->getId(), $media->getFile()?->getId());
        self::assertSame($thumbnail->getId(), $media->getThumbnail()?->getId());
        self::assertSame($photos->getId(), $media->getCategory()?->getId());
    }

    public function testTheFileIsRequiredAndTheThumbnailIsAnImage(): void
    {
        $notes = $this->files->text('notes.txt', 'notes');
        $this->client->loginUser($this->fixtures->user('editor@example.com', ['view medias', 'edit medias', 'view files']), 'admin');

        $crawler = $this->client->request('GET', '/admin/medias/new');
        $form = $crawler->filter('form[name="media"]')->form();
        $form['media[code]'] = 'notes';
        $form['media[thumbnail]'] = $notes->getId();
        $crawler = $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(1, $crawler->filter('#media_file.is-invalid'));
        self::assertCount(1, $crawler->filter('#media_thumbnail.is-invalid'));

        $media = new Media();
        $media->setCode('notes');
        $media->setFile($notes);
        $media->setThumbnail($notes);
        $violations = self::getContainer()->get(ValidatorInterface::class)->validate($media);
        self::assertCount(1, $violations);
        self::assertSame('thumbnail', $violations[0]->getPropertyPath());
    }

    public function testTheCodeIsRequiredAndUnique(): void
    {
        $this->fixtures->media($this->files->png('tractor.png'), 'Tractor', code: 'tractor');
        $notes = $this->files->text('notes.txt', 'notes');
        $this->client->loginUser($this->fixtures->user('editor@example.com', ['view medias', 'edit medias', 'view files']), 'admin');

        foreach (['', 'tractor'] as $code) {
            $crawler = $this->client->request('GET', '/admin/medias/new');
            $form = $crawler->filter('form[name="media"]')->form();
            $form['media[code]'] = $code;
            $form['media[file]'] = $notes->getId();
            $crawler = $this->client->submit($form);

            self::assertResponseStatusCodeSame(422);
            self::assertCount(1, $crawler->filter('#media_code.is-invalid'), \sprintf('Code "%s".', $code));
        }

        $crawler = $this->client->request('GET', '/admin/medias?filters[search]=tract');
        self::assertSame(['Tractor'], $this->names($crawler));
    }

    public function testEditKeepsTheName(): void
    {
        $media = $this->fixtures->media($this->files->png('tractor.png'), 'Tractor');
        $other = $this->files->png('other.png', 30, 30);
        $this->client->loginUser($this->fixtures->user('editor@example.com', ['view medias', 'edit medias', 'view files']), 'admin');

        $crawler = $this->client->request('GET', '/admin/medias/' . $media->getId() . '/edit');
        self::assertCount(1, $crawler->filter('#media_category option'), 'Only the placeholder.');
        $form = $crawler->filter('form[name="media"]')->form();
        $form['media[file]'] = $other->getId();
        $this->client->submit($form);

        self::assertResponseRedirects();
        self::assertSame('Tractor', $this->media()->getName());
        self::assertSame($other->getId(), $this->media()->getFile()?->getId());
    }

    public function testDeletingAMediaKeepsItsFiles(): void
    {
        $file = $this->files->text('brochure.txt', 'brochure');
        $thumbnail = $this->files->png('cover.png');
        $media = $this->fixtures->media($file, 'Brochure', thumbnail: $thumbnail);
        $this->client->loginUser($this->fixtures->user('deleter@example.com', ['view medias', 'delete medias']), 'admin');

        $button = $this->client->request('GET', '/admin/medias')
            ->filter('[data-gm-delete-url="/admin/medias/' . $media->getId() . '/delete"]');
        $this->client->request('POST', (string) $button->attr('data-gm-delete-url'), ['_token' => $button->attr('data-gm-delete-token')]);

        self::assertResponseRedirects('/admin/medias');
        $this->entityManager()->clear();
        self::assertSame(0, $this->entityManager()->getRepository(Media::class)->count());
        self::assertNotNull($this->entityManager()->find(File::class, $file->getId()));
        self::assertNotNull($this->entityManager()->find(File::class, $thumbnail->getId()));
    }

    public function testTheMediasNeedThePermission(): void
    {
        $this->client->loginUser($this->fixtures->user('nobody@example.com', ['view users']), 'admin');

        $this->client->request('GET', '/admin/medias');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->fixtures->user('viewer@example.com', ['view medias']), 'admin');
        $this->client->request('GET', '/admin/medias/new');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return list<string>
     */
    private function names(Crawler $crawler): array
    {
        return $crawler->filter('#gm-list-table tbody td:nth-child(2) .fw-medium')->each(static fn (Crawler $cell): string => $cell->text());
    }

    private function media(): Media
    {
        $this->entityManager()->clear();
        $media = $this->entityManager()->getRepository(Media::class)->findOneBy([]);
        self::assertInstanceOf(Media::class, $media);

        return $media;
    }
}
