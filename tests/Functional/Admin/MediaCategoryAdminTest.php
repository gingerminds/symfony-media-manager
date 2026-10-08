<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\Admin;

use Gingerminds\MediaManagerBundle\Entity\Media\Media;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaCategory;
use Gingerminds\MediaManagerBundle\Tests\Functional\ApiTestCase;
use Gingerminds\MediaManagerBundle\Tests\Functional\FileFactory;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MediaCategoryAdminTest extends ApiTestCase
{
    public function testTheTreeIsOrderedByPosition(): void
    {
        $photos = $this->fixtures->mediaCategory('photos', position: 1);
        $this->fixtures->mediaCategory('videos', position: 0);
        $this->fixtures->mediaCategory('portraits', $photos, 1);
        $this->fixtures->mediaCategory('landscapes', $photos, 0);
        $this->entityManager()->clear();
        $this->client->loginUser($this->fixtures->user('editor@example.com', ['view media_categories', 'edit media_categories']), 'admin');

        $crawler = $this->client->request('GET', '/admin/media-categories');

        self::assertResponseIsSuccessful();
        self::assertSame(['Videos', 'Photos', 'Landscapes', 'Portraits'], $this->names($crawler));
        self::assertSame(['', (string) $photos->getId()], $crawler->filter('.sortable-level')->each(static fn (Crawler $level): string => (string) $level->attr('data-parent-id')));
        self::assertCount(4, $crawler->filter('.drag-handle'));
        self::assertCount(1, $crawler->filter('[data-controller="gm-sortable-tree"][data-gm-sortable-tree-csrf-token-value]'));
        self::assertCount(1, $crawler->filter('a[href="/admin/media-categories/new?parent_id=' . $photos->getId() . '"]'));
    }

    public function testAViewerCannotReorderNorCreate(): void
    {
        $this->fixtures->mediaCategory('photos');
        $this->client->loginUser($this->fixtures->user('viewer@example.com', ['view media_categories']), 'admin');

        $crawler = $this->client->request('GET', '/admin/media-categories');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.drag-handle'));
        self::assertCount(0, $crawler->filter('[data-controller="gm-sortable-tree"]'));
        self::assertCount(0, $crawler->filter('a[href^="/admin/media-categories/new"]'));

        $this->client->request('GET', '/admin/media-categories/new');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/media-categories/reorder', content: '{"ids": [], "parent_id": null}');
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheMenuHasAMediaLibrarySection(): void
    {
        $this->client->loginUser($this->fixtures->user('menu@example.com', ['view media_categories']), 'admin');
        $crawler = $this->client->request('GET', '/admin/');
        self::assertCount(1, $crawler->filter('#gm-sidebar a[href="/admin/media-categories"]'));
        self::assertSelectorTextContains('#gm-sidebar', 'Media library');

        $this->client->loginUser($this->fixtures->user('nobody@example.com', ['view users']), 'admin');
        $crawler = $this->client->request('GET', '/admin/');
        self::assertCount(0, $crawler->filter('#gm-sidebar a[href="/admin/media-categories"]'));
        self::assertSelectorTextNotContains('#gm-sidebar', 'Media library');
    }

    public function testCreateAChildGoesToTheEndOfItsLevel(): void
    {
        $photos = $this->fixtures->mediaCategory('photos');
        $this->fixtures->mediaCategory('landscapes', $photos, 4);
        $this->entityManager()->clear();
        $this->client->loginUser($this->fixtures->user('creator@example.com', superAdmin: true), 'admin');

        $crawler = $this->client->request('GET', '/admin/media-categories/new?parent_id=' . $photos->getId());
        self::assertSame((string) $photos->getId(), $crawler->filter('#media_category_parent option[selected]')->attr('value'));
        self::assertSame(['— None —', 'Photos', '— Landscapes'], $crawler->filter('#media_category_parent option')->each(static fn (Crawler $option): string => $option->text()));

        $form = $crawler->filter('form[name="media_category"]')->form();
        $form['media_category[code]'] = 'portraits';
        $form['media_category[name]'] = 'Portraits';
        $this->client->submit($form);

        self::assertResponseRedirects();
        $portraits = $this->category('portraits');
        self::assertSame($photos->getId(), $portraits->getParentId());
        self::assertSame(5, $portraits->getPosition());
    }

    public function testMovingToAnotherParentGoesToTheEndOfItsLevel(): void
    {
        $this->fixtures->mediaCategory('photos', position: 0);
        $videos = $this->fixtures->mediaCategory('videos', position: 1);
        $clips = $this->fixtures->mediaCategory('clips', $videos, 0);
        $this->entityManager()->clear();
        $this->client->loginUser($this->fixtures->user('mover@example.com', superAdmin: true), 'admin');

        $crawler = $this->client->request('GET', '/admin/media-categories/' . $clips->getId() . '/edit');
        $form = $crawler->filter('form[name="media_category"]')->form();
        $form['media_category[parent]'] = '';
        $this->client->submit($form);
        self::assertResponseRedirects();
        self::assertSame(2, $this->category('clips')->getPosition());

        $crawler = $this->client->request('GET', '/admin/media-categories/' . $clips->getId() . '/edit');
        $form = $crawler->filter('form[name="media_category"]')->form();
        $form['media_category[name]'] = 'Video clips';
        $this->client->submit($form);
        self::assertSame(2, $this->category('clips')->getPosition(), 'Same parent, same position.');
    }

    public function testTheParentCannotBeTheCategoryNorOneOfItsDescendants(): void
    {
        $photos = $this->fixtures->mediaCategory('photos');
        $landscapes = $this->fixtures->mediaCategory('landscapes', $photos);
        $mountains = $this->fixtures->mediaCategory('mountains', $landscapes);
        $this->fixtures->mediaCategory('videos');
        $this->entityManager()->clear();
        $this->client->loginUser($this->fixtures->user('cycle@example.com', superAdmin: true), 'admin');

        $crawler = $this->client->request('GET', '/admin/media-categories/' . $photos->getId() . '/edit');
        self::assertSame(['— None —', 'Videos'], $crawler->filter('#media_category_parent option')->each(static fn (Crawler $option): string => $option->text()));

        $photos = $this->category('photos');
        $repository = $this->entityManager()->getRepository(MediaCategory::class);
        $photos->setParent($repository->find($mountains->getId()));
        $violations = self::getContainer()->get(ValidatorInterface::class)->validate($photos);
        self::assertCount(1, $violations);
        self::assertSame('parent', $violations[0]->getPropertyPath());

        $photos->setParent($photos);
        self::assertCount(1, self::getContainer()->get(ValidatorInterface::class)->validate($photos));
    }

    public function testTheCodeIsUnique(): void
    {
        $this->fixtures->mediaCategory('photos');
        $this->client->loginUser($this->fixtures->user('unique@example.com', superAdmin: true), 'admin');

        $crawler = $this->client->request('GET', '/admin/media-categories/new');
        $form = $crawler->filter('form[name="media_category"]')->form();
        $form['media_category[code]'] = 'photos';
        $form['media_category[name]'] = 'Other photos';
        $crawler = $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(1, $crawler->filter('#media_category_code.is-invalid'));
    }

    public function testACategoryWithChildrenCannotBeDeleted(): void
    {
        $photos = $this->fixtures->mediaCategory('photos');
        $landscapes = $this->fixtures->mediaCategory('landscapes', $photos);
        $this->entityManager()->clear();
        $this->client->loginUser($this->fixtures->user('deleter@example.com', superAdmin: true), 'admin');

        $this->delete($photos);
        self::assertResponseRedirects('/admin/media-categories');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'subcategories');
        self::assertSame(['Photos', 'Landscapes'], $this->names($crawler));

        $this->delete($landscapes);
        $this->delete($photos);
        self::assertSame([], $this->names($this->client->followRedirect()));
    }

    public function testACategoryWithMediasCannotBeDeleted(): void
    {
        $photos = $this->fixtures->mediaCategory('photos');
        $file = new FileFactory(self::getContainer()->get('test.file_storage'))->png();
        $media = $this->fixtures->media($file, 'Tractor', $photos);
        $this->client->disableReboot();
        $this->client->loginUser($this->fixtures->user('deleter@example.com', superAdmin: true), 'admin');

        $this->delete($photos);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'medias');
        self::assertSame(['Photos'], $this->names($crawler));

        $this->entityManager()->find(Media::class, $media->getId())?->setCategory(null);
        $this->entityManager()->flush();
        $this->delete($photos);
        self::assertSame([], $this->names($this->client->followRedirect()));
    }

    public function testReorderOneLevel(): void
    {
        $photos = $this->fixtures->mediaCategory('photos', position: 0);
        $videos = $this->fixtures->mediaCategory('videos', position: 1);
        $clips = $this->fixtures->mediaCategory('clips', $videos, 0);
        $this->client->loginUser($this->fixtures->user('sorter@example.com', ['view media_categories', 'edit media_categories']), 'admin');
        $token = (string) $this->client->request('GET', '/admin/media-categories')
            ->filter('[data-gm-sortable-tree-csrf-token-value]')->attr('data-gm-sortable-tree-csrf-token-value');

        $this->reorder(['ids' => [$videos->getId(), $photos->getId()], 'parent_id' => null], 'invalid');
        self::assertResponseStatusCodeSame(403);

        $this->reorder(['ids' => [$clips->getId(), $videos->getId(), $photos->getId()], 'parent_id' => null], $token);
        self::assertResponseIsSuccessful();

        self::assertSame(1, $this->category('videos')->getPosition());
        self::assertSame(2, $this->category('photos')->getPosition());
        self::assertSame(0, $this->category('clips')->getPosition(), 'Another level: ignored.');
    }

    /**
     * @return list<string>
     */
    private function names(Crawler $crawler): array
    {
        return $crawler->filter('.sortable-item .fw-medium')->each(static fn (Crawler $name): string => $name->text());
    }

    private function delete(MediaCategory $category): void
    {
        $button = $this->client->request('GET', '/admin/media-categories')
            ->filter('[data-gm-delete-url="/admin/media-categories/' . $category->getId() . '/delete"]');
        $this->client->request('POST', (string) $button->attr('data-gm-delete-url'), ['_token' => $button->attr('data-gm-delete-token')]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function reorder(array $payload, string $token): void
    {
        $this->client->request('POST', '/admin/media-categories/reorder', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $token,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    private function category(string $code): MediaCategory
    {
        $this->entityManager()->clear();
        $category = $this->entityManager()->getRepository(MediaCategory::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(MediaCategory::class, $category);

        return $category;
    }
}
