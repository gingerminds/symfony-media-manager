<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\Admin;

use Gingerminds\MediaManagerBundle\Entity\File\File;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\Tests\Application\Entity\Article;
use Gingerminds\MediaManagerBundle\Tests\Functional\ApiTestCase;
use Gingerminds\MediaManagerBundle\Tests\Functional\FileFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileLibraryAdminTest extends ApiTestCase
{
    private const array EDITOR = ['view files', 'edit files'];

    private FileFactory $files;
    private FileLibrary $library;
    private FilesystemOperator $filesystem;
    private string $token = '';

    protected function setUp(): void
    {
        parent::setUp();
        // Same container for every request: the files live in an in-memory storage.
        $this->client->disableReboot();
        $this->files = new FileFactory(self::getContainer()->get('test.file_storage'));
        $this->library = self::getContainer()->get('test.file_library');
        $this->filesystem = self::getContainer()->get('test.storage.default');
    }

    public function testTheAdminLayoutLoadsTheBundleAssets(): void
    {
        $crawler = $this->client->request('GET', '/admin/login');

        self::assertStringContainsString("app.register('gm-file-browser'", $crawler->filter('head script[type="module"]')->last()->text());
        self::assertStringContainsString("app.register('gm-file-picker'", $crawler->filter('head script[type="module"]')->last()->text());
        self::assertStringContainsString('/assets/gingerminds-media-manager/controllers/file_browser_controller-', $crawler->filter('head script[type="module"]')->last()->text());
        self::assertCount(1, $crawler->filter('head link[rel="stylesheet"][href*="gingerminds-media-manager/styles/media-manager"]'));
    }

    public function testAViewerBrowsesWithoutWriteActions(): void
    {
        $this->client->loginUser($this->fixtures->user('viewer@example.com', ['view files']), 'admin');

        $crawler = $this->client->request('GET', '/admin/files');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h4', 'File library');
        self::assertCount(1, $crawler->filter('#gm-sidebar a[href="/admin/files"]'));
        self::assertFalse($this->browserConfig($crawler->filter('[data-controller="gm-file-browser"]')->attr('data-gm-file-browser-config-value'))['canEdit']);
        self::assertCount(0, $crawler->filter('input[type="file"]'));

        $this->client->request('GET', '/admin/files/browse');
        self::assertResponseIsSuccessful();

        $this->client->request('POST', '/admin/files/directories', content: '{"parent": "", "name": "docs"}');
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheLibraryNeedsThePermission(): void
    {
        $this->client->loginUser($this->fixtures->user('nobody@example.com', ['view media_categories']), 'admin');

        $this->client->request('GET', '/admin/files');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/files/browse');
        self::assertResponseStatusCodeSame(403);

        $crawler = $this->client->request('GET', '/admin/');
        self::assertCount(0, $crawler->filter('#gm-sidebar a[href="/admin/files"]'));
    }

    public function testWritesNeedTheCsrfToken(): void
    {
        $this->client->loginUser($this->fixtures->user('editor@example.com', self::EDITOR), 'admin');

        $this->client->request('POST', '/admin/files/directories', server: ['HTTP_X_CSRF_TOKEN' => 'invalid'], content: '{"parent": "", "name": "docs"}');

        self::assertResponseStatusCodeSame(403);
    }

    public function testBrowse(): void
    {
        $this->library->mkdir('', 'docs');
        $this->library->mkdir('docs', 'old');
        $photo = $this->files->png('Photo.png');
        $report = $this->files->text('report.txt', 'report', 'docs');
        $this->files->text('archive.txt', 'archive', 'docs/old');
        $article = new Article('Tractor');
        $article->cover = $report;
        $this->entityManager()->persist($article);
        $this->entityManager()->flush();
        $this->login();

        $root = $this->json('GET', '/admin/files/browse');
        self::assertSame('', $root['path']);
        self::assertSame([['name' => 'docs', 'path' => 'docs', 'hasChildren' => true]], $root['directories']);
        self::assertSame(['Photo.png'], array_column($root['files'], 'name'));
        self::assertSame('/api/files/' . $photo->getId(), $root['files'][0]['url']);
        self::assertSame('/api/files/' . $photo->getId() . '/thumbnail', $root['files'][0]['thumbnailUrl']);
        self::assertSame('/api/files/' . $photo->getId() . '/card', $root['files'][0]['previewUrl']);
        self::assertSame(['page' => 1, 'pages' => 1, 'itemsPerPage' => 48, 'totalItems' => 1], $root['pagination']);

        $docs = $this->json('GET', '/admin/files/browse?path=docs');
        self::assertSame([['name' => 'docs', 'path' => 'docs']], $docs['breadcrumb']);
        self::assertSame([['name' => 'old', 'path' => 'docs/old', 'hasChildren' => false]], $docs['directories']);
        self::assertSame(['report.txt'], array_column($docs['files'], 'name'));
        self::assertSame(1, $docs['files'][0]['usages']);
        self::assertSame('docs', $docs['files'][0]['directory']);
        self::assertNull($docs['files'][0]['thumbnailUrl']);
        self::assertNull($docs['files'][0]['previewUrl']);

        $search = $this->json('GET', '/admin/files/browse?path=docs&recursive=1&sort=name&direction=desc&type=document');
        self::assertSame(['report.txt', 'archive.txt'], array_column($search['files'], 'name'));

        self::assertSame([['name' => 'old', 'path' => 'docs/old', 'hasChildren' => false]], $this->json('GET', '/admin/files/directories?path=docs'));
    }

    public function testThePickerOnlyListsTheAcceptedTypes(): void
    {
        $this->files->png('photo.png');
        $this->files->svg('logo.svg');
        $this->files->text('notes.txt', 'notes');
        $this->login();

        $images = $this->json('GET', '/admin/files/browse?accept[]=image/*&accept[]=invalid');
        self::assertSame(['logo.svg', 'photo.png'], array_column($images['files'], 'name'));

        self::assertSame(['notes.txt'], array_column($this->json('GET', '/admin/files/browse?accept[]=text/plain')['files'], 'name'));
    }

    public function testThePickerModal(): void
    {
        $this->client->loginUser($this->fixtures->user('picker@example.com', ['view files']), 'admin');

        $crawler = $this->client->request('GET', '/admin/files/picker');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#gm-file-picker-modal [data-controller="gm-file-browser"][data-gm-file-browser-mode-value="picker"]'));
        self::assertCount(1, $crawler->filter('[data-gm-file-browser-target="pickConfirm"]'));
        self::assertCount(0, $crawler->filter('[data-gm-file-browser-target="directoryActions"], [data-gm-file-browser-target="selectAll"]'));

        $this->client->loginUser($this->fixtures->user('nobody@example.com', ['view media_categories']), 'admin');
        $this->client->request('GET', '/admin/files/picker');
        self::assertResponseStatusCodeSame(403);
    }

    public function testErrorsAreTranslated(): void
    {
        $this->login();

        $this->json('GET', '/admin/files/browse?path=missing');
        $this->assertStatus(422);
        self::assertSame(['error' => 'The folder "missing" does not exist.'], $this->response());

        $this->json('GET', '/admin/files/browse?path=../private');
        $this->assertStatus(422);
        self::assertSame(['error' => 'This path is not valid.'], $this->response());
    }

    public function testShowListsTheUsagesAndTheDuplicates(): void
    {
        $file = $this->files->text('logo.txt', 'logo');
        $copy = $this->files->text('logo-copy.txt', 'logo');
        $article = new Article('Tractor');
        $article->cover = $file;
        $this->entityManager()->persist($article);
        $this->entityManager()->flush();
        $this->login();

        $shown = $this->json('GET', '/admin/files/' . $file->getId());

        self::assertSame('logo.txt', $shown['name']);
        self::assertSame(1, $shown['usages']);
        self::assertSame('Tractor', $shown['usageList'][0]['title']);
        self::assertSame('/admin/articles/' . $article->getId() . '/edit', $shown['usageList'][0]['editUrl']);
        self::assertSame([$copy->getId()], array_column($shown['duplicates'], 'id'));

        $this->json('GET', '/admin/files/0190a8b2-3c4d-7e5f-8a9b-0c1d2e3f4a5b');
        $this->assertStatus(404);
    }

    public function testUpload(): void
    {
        $this->library->mkdir('', 'docs');
        $token = $this->login();

        $this->upload($token, $this->files->upload('Mon rapport.txt', 'report'), 'docs');
        $this->assertStatus(201);
        $uploaded = $this->response();
        self::assertFalse($uploaded['duplicate']);
        self::assertSame('docs', $uploaded['file']['directory']);
        self::assertTrue($this->filesystem->fileExists('library/docs/mon-rapport.txt'));

        $this->upload($token, $this->files->upload('copy.txt', 'report'));
        $this->assertStatus(200);
        self::assertTrue($this->response()['duplicate']);
        self::assertSame($uploaded['file']['id'], $this->response()['file']['id']);

        $this->upload($token, $this->files->upload('page.html', '<html><body>page</body></html>'));
        $this->assertStatus(422);
        self::assertSame('The file type "text/html" is not allowed.', $this->response()['error']);

        $this->client->request('POST', '/admin/files/upload', server: ['HTTP_X_CSRF_TOKEN' => $token, 'HTTP_ACCEPT' => 'application/json']);
        $this->assertStatus(422);
    }

    public function testDirectories(): void
    {
        $this->login();

        self::assertSame(['name' => 'actualites-2026', 'path' => 'actualites-2026'], $this->json('POST', '/admin/files/directories', ['parent' => '', 'name' => 'Actualités 2026']));
        $this->assertStatus(201);

        $this->json('POST', '/admin/files/directories', ['parent' => '', 'name' => 'Actualités 2026']);
        $this->assertStatus(422);
        self::assertSame('The folder "actualites-2026" already exists.', $this->response()['error']);

        $this->files->text('news.txt', 'news', 'actualites-2026');
        self::assertSame(['deleted' => [], 'kept' => ['actualites-2026']], $this->json('DELETE', '/admin/files/directories', ['paths' => ['actualites-2026']]));
        $this->assertStatus(422);

        $this->library->mkdir('', 'empty');
        self::assertSame(['deleted' => ['empty'], 'kept' => ['actualites-2026']], $this->json('DELETE', '/admin/files/directories', ['paths' => ['empty', 'actualites-2026']]));
        $this->assertStatus(200);
        self::assertFalse($this->filesystem->directoryExists('library/empty'));

        $this->json('DELETE', '/admin/files/directories', ['paths' => ['']]);
        $this->assertStatus(422);
        self::assertSame('The library root cannot be deleted.', $this->response()['error']);
    }

    public function testMoveDirectories(): void
    {
        $this->library->mkdir('', 'docs');
        $this->library->mkdir('', 'photos');
        $this->library->mkdir('', 'archives');
        $notes = $this->files->text('notes.txt', 'notes', 'docs');
        $this->login();

        self::assertSame(
            [['name' => 'docs', 'path' => 'archives/docs'], ['name' => 'photos', 'path' => 'archives/photos']],
            $this->json('PATCH', '/admin/files/directories', ['paths' => ['docs', 'photos'], 'parent' => 'archives']),
        );
        $this->assertStatus(200);
        self::assertSame(
            [['name' => 'docs-2026', 'path' => 'archives/docs-2026']],
            $this->json('PATCH', '/admin/files/directories', ['paths' => ['archives/docs'], 'parent' => 'archives', 'name' => 'Docs 2026']),
        );
        self::assertSame('library/archives/docs-2026/notes.txt', $this->entityManager()->find(File::class, $notes->getId())?->getPath());

        $this->json('PATCH', '/admin/files/directories', ['paths' => ['archives'], 'parent' => 'archives/docs-2026']);
        $this->assertStatus(422);
        self::assertSame('The folder "archives" cannot be moved into itself or one of its subfolders.', $this->response()['error']);

        // library.max_directory_move is 3 in the test application: the files of every folder count.
        foreach (['a', 'b'] as $name) {
            $this->files->text($name . '.txt', 'content ' . $name, 'archives/docs-2026');
        }

        $this->files->text('c.txt', 'content c', 'archives/photos');
        $this->json('PATCH', '/admin/files/directories', ['paths' => ['archives/docs-2026', 'archives/photos'], 'parent' => '']);
        $this->assertStatus(422);
        self::assertSame('"archives/docs-2026, archives/photos" holds 4 files, above the limit of 3: use the "gingerminds:media:directory:move" command.', $this->response()['error']);

        $this->client->loginUser($this->fixtures->user('viewer@example.com', ['view files']), 'admin');
        $this->json('PATCH', '/admin/files/directories', ['paths' => ['archives'], 'parent' => '']);
        $this->assertStatus(403);
    }

    public function testRenameAndMove(): void
    {
        $this->library->mkdir('', 'archive');
        $file = $this->files->text('report.txt', 'report');
        $this->login();

        $renamed = $this->json('PATCH', '/admin/files/' . $file->getId(), ['name' => 'Final report']);
        self::assertResponseIsSuccessful();
        self::assertSame('Final report.txt', $renamed['name']);
        self::assertTrue($this->filesystem->fileExists('library/final-report.txt'));

        $moved = $this->json('POST', '/admin/files/move', ['ids' => [$file->getId(), 'unknown'], 'path' => 'archive']);
        self::assertResponseIsSuccessful();
        self::assertSame(['archive'], array_column($moved, 'directory'));
        self::assertTrue($this->filesystem->fileExists('library/archive/final-report.txt'));

        $this->json('POST', '/admin/files/move', ['ids' => [$file->getId()], 'path' => 'missing']);
        $this->assertStatus(422);
    }

    public function testDeleteKeepsTheUsedFiles(): void
    {
        $used = $this->files->text('used.txt', 'used');
        $free = $this->files->text('free.txt', 'free');
        $article = new Article('Tractor');
        $article->cover = $used;
        $this->entityManager()->persist($article);
        $this->entityManager()->flush();
        $this->login();

        $result = $this->json('POST', '/admin/files/delete', ['ids' => [$used->getId(), $free->getId()]]);
        $this->assertStatus(200);
        self::assertSame([$free->getId()], $result['deleted']);
        self::assertSame([$used->getId()], array_column($result['blocked'], 'id'));
        self::assertSame('Tractor', $result['blocked'][0]['usageList'][0]['title']);

        $this->json('POST', '/admin/files/delete', ['ids' => [$used->getId()]]);
        $this->assertStatus(422);
        self::assertTrue($this->filesystem->fileExists('library/used.txt'));
    }

    public function testMergeTheDuplicates(): void
    {
        $keep = $this->files->text('logo.txt', 'logo');
        $copy = $this->files->text('logo-copy.txt', 'logo');
        $other = $this->files->text('other.txt', 'other');
        $article = new Article('Tractor');
        $article->cover = $copy;
        $this->entityManager()->persist($article);
        $this->entityManager()->flush();
        $articleId = $article->getId();
        $this->login();

        $this->json('POST', '/admin/files/merge', ['keep' => $keep->getId(), 'ids' => [$other->getId()]]);
        $this->assertStatus(422);
        self::assertSame('These files are not duplicates.', $this->response()['error']);

        self::assertSame(1, $this->json('POST', '/admin/files/merge', ['keep' => $keep->getId()])['updated']);
        $this->entityManager()->clear();
        self::assertSame($keep->getId(), $this->entityManager()->find(Article::class, $articleId)?->cover?->getId());
        self::assertNull($this->entityManager()->find(File::class, $copy->getId()));
    }

    /**
     * Logs an editor in.
     *
     * @return string the CSRF token of the library
     */
    private function login(): string
    {
        $this->client->loginUser($this->fixtures->user('editor@example.com', self::EDITOR), 'admin');
        $crawler = $this->client->request('GET', '/admin/files');
        $config = $this->browserConfig($crawler->filter('[data-controller="gm-file-browser"]')->attr('data-gm-file-browser-config-value'));
        self::assertTrue($config['canEdit']);

        return $this->token = $config['csrfToken'];
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<mixed>
     */
    private function json(string $method, string $uri, ?array $body = null): array
    {
        $this->client->request($method, $uri, server: [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $this->token,
        ], content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR));

        return $this->response();
    }

    private function upload(string $token, UploadedFile $file, string $path = ''): void
    {
        $this->client->request('POST', '/admin/files/upload', ['path' => $path], ['file' => $file], ['HTTP_X_CSRF_TOKEN' => $token, 'HTTP_ACCEPT' => 'application/json']);
    }

    /**
     * @return array<mixed>
     */
    private function response(): array
    {
        $content = (string) $this->client->getResponse()->getContent();

        return '' === $content ? [] : json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function browserConfig(?string $json): array
    {
        return json_decode((string) $json, true, flags: \JSON_THROW_ON_ERROR);
    }
}
