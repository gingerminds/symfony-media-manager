<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\Admin;

use Gingerminds\MediaManagerBundle\Entity\File\File;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\Tests\Application\Entity\Article;
use Gingerminds\MediaManagerBundle\Tests\Functional\ApiTestCase;
use Gingerminds\MediaManagerBundle\Tests\Functional\FileFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DomCrawler\Crawler;
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

    public function testTheAdminLayoutLoadsTheBundleStylesheet(): void
    {
        $crawler = $this->client->request('GET', '/admin/login');

        self::assertCount(1, $crawler->filter('head link[rel="stylesheet"][href*="gingerminds-media-manager/styles/media-manager"]'));
        self::assertStringNotContainsString('gingerminds-media-manager/controllers/', $crawler->filter('head script[type="module"]')->last()->text(), 'Controllers loaded by controllers.json.');
    }

    public function testAViewerBrowsesWithoutWriteActions(): void
    {
        $this->files->text('notes.txt', 'notes');
        $this->client->loginUser($this->fixtures->user('viewer@example.com', ['view files']), 'admin');

        $crawler = $this->client->request('GET', '/admin/files');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h4', 'File library');
        self::assertCount(1, $crawler->filter('#gm-sidebar a[href="/admin/files"]'));
        self::assertSame(['notes.txt'], $this->names($crawler));
        self::assertCount(0, $crawler->filter('input[type="file"], .gm-file-card-check, [data-controller~="gm-file-upload"]'));

        $this->client->request('POST', '/admin/files/directories', ['parent' => '', 'name' => 'docs']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheLibraryNeedsThePermission(): void
    {
        $this->client->loginUser($this->fixtures->user('nobody@example.com', ['view media_categories']), 'admin');

        foreach (['/admin/files', '/admin/files/tree', '/admin/files/picker'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }

        $crawler = $this->client->request('GET', '/admin/');
        self::assertCount(0, $crawler->filter('#gm-sidebar a[href="/admin/files"]'));
    }

    public function testWritesNeedTheCsrfToken(): void
    {
        $this->client->loginUser($this->fixtures->user('editor@example.com', self::EDITOR), 'admin');

        $this->client->request('POST', '/admin/files/directories', ['_token' => 'invalid', 'parent' => '', 'name' => 'docs']);

        // #[IsCsrfTokenValid]: not a resource route, the admin firewall handles it.
        self::assertResponseRedirects('/admin/login');
        self::assertSame([], $this->library->directories(''));
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

        $root = $this->client->request('GET', '/admin/files?path=');
        self::assertSame(['docs'], $this->directories($root));
        self::assertSame(['Photo.png'], $this->names($root));
        self::assertSame('/api/files/' . $photo->getId() . '/thumbnail', $root->filter('.gm-file-card[data-file-id] img')->attr('src'));
        self::assertSame('1 file(s)', $root->filter('.gm-file-browser-toolbar span')->first()->text());
        self::assertCount(1, $root->filter('turbo-frame#gm-file-browser[data-turbo-action="advance"]'));

        $docs = $this->client->request('GET', '/admin/files?path=docs');
        self::assertSame('docs', $docs->filter('.gm-file-browser-path .breadcrumb-item.active')->text());
        self::assertSame(['old'], $this->directories($docs));
        self::assertSame(['report.txt'], $this->names($docs));
        self::assertSame('1', trim($docs->filter('.gm-file-card[data-file-id] .badge')->text()));
        // The open branch of the tree: docs is open, its subfolders are listed.
        self::assertCount(1, $docs->filter('.gm-file-tree details[open] a[href="/admin/files?path=docs/old"]'));
        // A folder without subfolders has nothing to open: no caret, no lazy frame.
        self::assertCount(0, $docs->filter('.gm-file-tree summary a[href="/admin/files?path=docs/old"], turbo-frame#gm-file-tree-docs--old'));
        self::assertCount(1, $docs->filter('.gm-file-tree div.gm-file-tree-item a[href="/admin/files?path=docs/old"]'));
        // A closed folder with subfolders can be opened.
        self::assertCount(1, $root->filter('.gm-file-tree details:not([open]) turbo-frame#gm-file-tree-docs'));

        $search = $this->client->request('GET', '/admin/files?path=docs&recursive=1&sort=name_desc&type=document');
        self::assertSame(['report.txt', 'archive.txt'], $this->names($search));
        self::assertSame([], $this->directories($this->client->request('GET', '/admin/files?path=docs&search=report')), 'A search lists files only.');

        $tree = $this->client->request('GET', '/admin/files/tree?node=docs');
        self::assertCount(1, $tree->filter('turbo-frame#gm-file-tree-docs a[href="/admin/files?path=docs/old"]'));
        self::assertCount(0, $tree->filter('details'), 'docs/old has no subfolder.');
        $chooser = $this->client->request('GET', '/admin/files/tree?node=docs&chooser=1');
        self::assertCount(1, $chooser->filter('turbo-frame#gm-file-chooser-docs input[type="radio"][name="target"][value="docs/old"]'));
    }

    public function testThePickerOnlyListsTheAcceptedTypes(): void
    {
        $photo = $this->files->png('photo.png');
        $this->files->svg('logo.svg');
        $this->files->text('notes.txt', 'notes');
        $this->client->loginUser($this->fixtures->user('picker@example.com', self::EDITOR), 'admin');

        $images = $this->client->request('GET', '/admin/files/picker?path=&accept[]=image/*&accept[]=invalid');
        self::assertSame(['logo.svg', 'photo.png'], $this->names($images));
        self::assertSame(['image'], $images->filter('#gm-file-browser-type option[value!=""]')->each(static fn (Crawler $option): string => (string) $option->attr('value')));
        $picked = json_decode((string) $images->filter('[data-pick-id="' . $photo->getId() . '"] [data-gm-pick-item-param]')->attr('data-gm-pick-item-param'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['id' => $photo->getId(), 'name' => 'photo.png'], array_intersect_key($picked, ['id' => true, 'name' => true]));

        self::assertSame(['notes.txt'], $this->names($this->client->request('GET', '/admin/files/picker?path=&accept[]=text/plain')));
    }

    public function testThePickerModal(): void
    {
        $this->files->text('notes.txt', 'notes');
        $this->client->loginUser($this->fixtures->user('picker@example.com', self::EDITOR), 'admin');

        $crawler = $this->client->request('GET', '/admin/files/picker?dialog=delete');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#gm-file-picker-modal[data-controller="gm-pick"] turbo-frame#gm-file-browser'));
        self::assertCount(0, $crawler->filter('turbo-frame[data-turbo-action]'), 'The modal keeps the page URL.');
        self::assertCount(1, $crawler->filter('[data-gm-pick-target="confirm"]'));
        self::assertCount(1, $crawler->filter('input[type="file"]'), 'Uploads are offered.');
        self::assertCount(0, $crawler->filter('.gm-file-card-check, .gm-file-dialog'), 'No selection, no dialog but the new folder.');
        self::assertCount(1, $this->client->request('GET', '/admin/files/picker?dialog=mkdir')->filter('.gm-file-dialog'));
    }

    public function testErrorsAreFlashed(): void
    {
        $this->files->text('notes.txt', 'notes');
        $this->login();

        $crawler = $this->client->request('GET', '/admin/files?path=missing');
        self::assertResponseIsSuccessful();
        self::assertSame(['The folder "missing" does not exist.'], $this->flashes($crawler, 'danger'));
        self::assertSame(['notes.txt'], $this->names($crawler), 'The root instead.');

        self::assertSame(['This path is not valid.'], $this->flashes($this->client->request('GET', '/admin/files?path=../private'), 'danger'));
    }

    public function testTheDetailListsTheUsagesAndTheDuplicates(): void
    {
        $file = $this->files->text('logo.txt', 'logo');
        $this->files->text('logo-copy.txt', 'logo');
        $article = new Article('Tractor');
        $article->cover = $file;
        $this->entityManager()->persist($article);
        $this->entityManager()->flush();
        $this->login();

        $crawler = $this->client->request('GET', '/admin/files?path=&file=' . $file->getId());

        $detail = $crawler->filter('.gm-file-browser-detail');
        self::assertSame('logo.txt', $detail->filter('h6')->text());
        self::assertCount(1, $crawler->filter('.gm-file-card.is-active[data-file-id="' . $file->getId() . '"]'));
        self::assertSame('Tractor', trim($detail->filter('#gm-file-detail-usages a')->text()));
        self::assertSame('/admin/articles/' . $article->getId() . '/edit', $detail->filter('#gm-file-detail-usages a')->attr('href'));
        self::assertSame('logo-copy.txt', $detail->filter('#gm-file-detail-duplicates a')->text());
        self::assertCount(1, $detail->filter('form[action="/admin/files/merge"] input[name="keep"][value="' . $file->getId() . '"]'));
        self::assertCount(0, $this->client->request('GET', '/admin/files?file=0190a8b2-3c4d-7e5f-8a9b-0c1d2e3f4a5b')->filter('.gm-file-browser-detail'));
    }

    public function testUpload(): void
    {
        $this->library->mkdir('', 'docs');
        $this->login();

        $this->upload($this->files->upload('Mon rapport.txt', 'report'), 'docs');
        $this->assertStatus(201);
        $uploaded = $this->response();
        self::assertFalse($uploaded['duplicate']);
        self::assertSame('docs', $uploaded['file']['directory']);
        self::assertTrue($this->filesystem->fileExists('library/docs/mon-rapport.txt'));

        $this->upload($this->files->upload('copy.txt', 'report'));
        $this->assertStatus(200);
        self::assertTrue($this->response()['duplicate']);
        self::assertSame($uploaded['file']['id'], $this->response()['file']['id']);

        $this->upload($this->files->upload('page.html', '<html><body>page</body></html>'));
        $this->assertStatus(422);
        self::assertSame('page.html : The file type "text/html" is not allowed.', $this->response()['error']);

        $this->client->request('POST', '/admin/files/upload', ['_token' => $this->token], server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertStatus(422);

        // The messages of the uploads, shown by the reloaded browser.
        $crawler = $this->client->request('GET', '/admin/files?path=docs');
        self::assertSame(['"Mon rapport.txt" has been uploaded.'], $this->flashes($crawler, 'success'));
        self::assertSame(['"copy.txt" is already in the library: "docs/Mon rapport.txt".'], $this->flashes($crawler, 'info'));
    }

    public function testDirectories(): void
    {
        $this->login();

        $crawler = $this->post('/admin/files/directories', ['parent' => '', 'name' => 'Actualités 2026'], '/admin/files?path=');
        self::assertSame(['actualites-2026'], $this->directories($crawler));

        $crawler = $this->post('/admin/files/directories', ['parent' => '', 'name' => 'Actualités 2026']);
        self::assertSame(['The folder "actualites-2026" already exists.'], $this->flashes($crawler, 'danger'));

        $this->files->text('news.txt', 'news', 'actualites-2026');
        $this->library->mkdir('', 'empty');
        $crawler = $this->post('/admin/files/delete', ['paths' => ['empty', 'actualites-2026']]);
        self::assertSame(['1 folder(s) deleted.'], $this->flashes($crawler, 'success'));
        self::assertSame(['These folders are not empty, they have not been deleted: actualites-2026'], $this->flashes($crawler, 'warning'));
        self::assertFalse($this->filesystem->directoryExists('library/empty'));

        self::assertSame(['The library root cannot be deleted.'], $this->flashes($this->post('/admin/files/delete', ['paths' => ['']]), 'danger'));
    }

    public function testMoveDirectories(): void
    {
        $this->library->mkdir('', 'docs');
        $this->library->mkdir('', 'photos');
        $this->library->mkdir('', 'archives');
        $notes = $this->files->text('notes.txt', 'notes', 'docs');
        $this->login();

        $crawler = $this->post('/admin/files/move', ['paths' => ['docs', 'photos'], 'target' => 'archives'], '/admin/files?path=archives');
        self::assertSame(['docs', 'photos'], $this->directories($crawler));
        $crawler = $this->post('/admin/files/directories/rename', ['path' => 'archives/docs', 'name' => 'Docs 2026'], '/admin/files?path=archives');
        self::assertSame(['The folder is now named "docs-2026".'], $this->flashes($crawler, 'success'));
        self::assertSame('library/archives/docs-2026/notes.txt', $this->entityManager()->find(File::class, $notes->getId())?->getPath());

        $crawler = $this->post('/admin/files/move', ['paths' => ['archives'], 'target' => 'archives/docs-2026']);
        self::assertSame(['The folder "archives" cannot be moved into itself or one of its subfolders.'], $this->flashes($crawler, 'danger'));

        // library.max_directory_move is 3 in the test application: the files of every folder count.
        foreach (['a', 'b'] as $name) {
            $this->files->text($name . '.txt', 'content ' . $name, 'archives/docs-2026');
        }

        $this->files->text('c.txt', 'content c', 'archives/photos');
        $crawler = $this->post('/admin/files/move', ['paths' => ['archives/docs-2026', 'archives/photos'], 'target' => '']);
        self::assertSame(['"archives/docs-2026, archives/photos" holds 4 files, above the limit of 3: use the "gingerminds:media:directory:move" command.'], $this->flashes($crawler, 'danger'));

        $this->client->loginUser($this->fixtures->user('viewer@example.com', ['view files']), 'admin');
        $this->client->request('POST', '/admin/files/move', ['_token' => $this->token, 'paths' => ['archives'], 'target' => '']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testRenameAndMoveFiles(): void
    {
        $this->library->mkdir('', 'archive');
        $file = $this->files->text('report.txt', 'report');
        $this->login();

        $this->post('/admin/files/' . $file->getId(), ['name' => 'Final report']);
        self::assertTrue($this->filesystem->fileExists('library/final-report.txt'));

        $crawler = $this->post('/admin/files/move', ['ids' => [$file->getId(), 'unknown'], 'target' => 'archive']);
        self::assertSame(['1 file(s) moved.'], $this->flashes($crawler, 'success'));
        self::assertTrue($this->filesystem->fileExists('library/archive/final-report.txt'));

        self::assertSame(['The folder "missing" does not exist.'], $this->flashes($this->post('/admin/files/move', ['ids' => [$file->getId()], 'target' => 'missing']), 'danger'));

        $this->client->request('POST', '/admin/files/0190a8b2-3c4d-7e5f-8a9b-0c1d2e3f4a5b', ['_token' => $this->token, 'name' => 'x']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testUpdateOnlyChangesTheSentFields(): void
    {
        $photo = $this->files->png('photo.png');
        $this->login();

        self::assertSame(['The alternative text has been saved.'], $this->flashes($this->post('/admin/files/' . $photo->getId(), ['alt' => '  Red tractor  ']), 'success'));
        self::assertSame(['Red tractor', 'photo.png'], $this->altAndName($photo));

        $this->post('/admin/files/' . $photo->getId(), ['name' => 'Tractor']);
        self::assertSame(['Red tractor', 'Tractor.png'], $this->altAndName($photo));

        $this->post('/admin/files/' . $photo->getId(), ['name' => 'Field', 'alt' => ' ']);
        self::assertSame([null, 'Field.png'], $this->altAndName($photo));

        $crawler = $this->post('/admin/files/' . $photo->getId(), ['alt' => str_repeat('a', 256)]);
        self::assertSame(['The alternative text cannot exceed 255 characters.'], $this->flashes($crawler, 'danger'));
        self::assertSame([null, 'Field.png'], $this->altAndName($photo));
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

        $crawler = $this->post('/admin/files/delete', ['ids' => [$used->getId(), $free->getId()]]);
        self::assertSame(['1 file(s) deleted.'], $this->flashes($crawler, 'success'));
        self::assertSame(['These files are used, they have not been deleted: used.txt (1)'], $this->flashes($crawler, 'warning'));
        self::assertSame(['used.txt'], $this->names($crawler));
        self::assertTrue($this->filesystem->fileExists('library/used.txt'));
    }

    public function testMergeTheDuplicates(): void
    {
        $keep = $this->files->text('logo.txt', 'logo');
        $copy = $this->files->text('logo-copy.txt', 'logo');
        $article = new Article('Tractor');
        $article->cover = $copy;
        $this->entityManager()->persist($article);
        $this->entityManager()->flush();
        $articleId = $article->getId();
        $this->login();

        $crawler = $this->post('/admin/files/merge', ['keep' => $keep->getId()]);
        self::assertSame(['Duplicates merged, 1 reference(s) updated.'], $this->flashes($crawler, 'success'));
        $this->entityManager()->clear();
        self::assertSame($keep->getId(), $this->entityManager()->find(Article::class, $articleId)?->cover?->getId());
        self::assertNull($this->entityManager()->find(File::class, $copy->getId()));
    }

    public function testTheDialogs(): void
    {
        $this->library->mkdir('', 'docs');
        $this->library->mkdir('', 'photos');
        $file = $this->files->text('notes.txt', 'notes');
        $this->login();

        // The selection enables the toolbar actions (gm-file-selection), disabled until then.
        $root = $this->client->request('GET', '/admin/files?path=');
        self::assertSame(['move', 'rename', 'delete'], $root->filter('.gm-file-browser-toolbar button[disabled][name="dialog"]')->each(static fn (Crawler $button): string => (string) $button->attr('value')));
        self::assertCount(0, $root->filter('.gm-file-dialog'));

        $move = $this->client->request('GET', '/admin/files?path=&dialog=move&ids[]=' . $file->getId() . '&paths[]=docs');
        self::assertCount(1, $move->filter('.modal.gm-file-dialog[data-controller="gm-file-dialog"] a[data-gm-file-dialog-target="cancel"][href="/admin/files?path="]'));
        $form = $move->filter('.gm-file-dialog form[action="/admin/files/move"]');
        self::assertCount(1, $form->filter('input[name="ids[]"][value="' . $file->getId() . '"]'));
        self::assertCount(1, $form->filter('input[name="paths[]"][value="docs"]'));
        self::assertSame(['', 'docs', 'photos'], $form->filter('input[type="radio"][name="target"]')->each(static fn (Crawler $radio): string => (string) $radio->attr('value')));
        self::assertSame('/admin/files?path=', $form->filter('input[name="return"]')->attr('value'));

        $rename = $this->client->request('GET', '/admin/files?path=&dialog=rename&paths[]=docs&paths[]=photos');
        self::assertStringContainsString('Select one folder', $rename->filter('.gm-file-dialog')->text());
        $rename = $this->client->request('GET', '/admin/files?path=&dialog=rename&paths[]=docs');
        self::assertSame('docs', $rename->filter('.gm-file-dialog input[name="name"]')->attr('value'));

        $delete = $this->client->request('GET', '/admin/files?path=&dialog=delete&ids[]=' . $file->getId());
        self::assertStringContainsString('notes.txt', $delete->filter('.gm-file-dialog')->text());
        self::assertCount(1, $delete->filter('.gm-file-dialog form[action="/admin/files/delete"] button.btn-danger'));

        self::assertCount(0, $this->client->request('GET', '/admin/files?dialog=unknown')->filter('.gm-file-dialog'));
    }

    public function testTheWritesGoBackToTheBrowserOnly(): void
    {
        $this->login();

        $this->client->request('POST', '/admin/files/directories', ['_token' => $this->token, 'parent' => '', 'name' => 'docs', 'return' => '/admin/files?path=docs']);
        self::assertResponseRedirects('/admin/files?path=docs', 303);

        $this->client->request('POST', '/admin/files/directories', ['_token' => $this->token, 'parent' => '', 'name' => 'other', 'return' => 'https://example.com/admin/files']);
        self::assertResponseRedirects('/admin/files', 303);
    }

    /**
     * Logs an editor in and reads the CSRF token of the library.
     */
    private function login(): void
    {
        $this->client->loginUser($this->fixtures->user('editor@example.com', self::EDITOR), 'admin');
        $crawler = $this->client->request('GET', '/admin/files');
        $this->token = (string) $crawler->filter('[data-gm-file-upload-token-value]')->attr('data-gm-file-upload-token-value');
        self::assertNotSame('', $this->token);
    }

    /**
     * Posts a form of the browser and follows its redirection.
     *
     * @param array<string, mixed> $parameters
     */
    private function post(string $url, array $parameters, string $return = '/admin/files?path='): Crawler
    {
        $this->client->request('POST', $url, ['_token' => $this->token, 'return' => $return, ...$parameters]);
        self::assertResponseRedirects($return, 303);

        return $this->client->followRedirect();
    }

    private function upload(UploadedFile $file, string $path = ''): void
    {
        $this->client->request('POST', '/admin/files/upload', ['_token' => $this->token, 'path' => $path], ['file' => $file], ['HTTP_ACCEPT' => 'application/json']);
    }

    /**
     * @return list<string>
     */
    private function names(Crawler $crawler): array
    {
        return $crawler->filter('.gm-file-card[data-file-id] .gm-file-card-name')->each(static fn (Crawler $name): string => $name->text());
    }

    /**
     * @return list<string>
     */
    private function directories(Crawler $crawler): array
    {
        return $crawler->filter('.gm-file-card-directory .gm-file-card-name')->each(static fn (Crawler $name): string => $name->text());
    }

    /**
     * @return list<string>
     */
    private function flashes(Crawler $crawler, string $type): array
    {
        return $crawler->filter('turbo-frame#gm-file-browser .alert-' . $type)->each(static fn (Crawler $alert): string => trim($alert->text()));
    }

    /**
     * @return array{string|null, string}
     */
    private function altAndName(File $file): array
    {
        $this->entityManager()->clear();
        $row = $this->entityManager()->find(File::class, $file->getId());
        self::assertInstanceOf(File::class, $row);

        return [$row->getAlt(), $row->getOriginalName()];
    }

    /**
     * @return array<mixed>
     */
    private function response(): array
    {
        $content = (string) $this->client->getResponse()->getContent();

        return '' === $content ? [] : json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }
}
