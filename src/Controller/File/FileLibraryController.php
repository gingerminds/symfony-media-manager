<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\File;

use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Gingerminds\CoreBundle\Service\DisplayTimezone;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Exception\TranslatableExceptionInterface;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\File\FileLibraryPresenter;
use Gingerminds\MediaManagerBundle\File\LibraryQuery;
use Gingerminds\MediaManagerBundle\File\LibraryStartPathProviderInterface;
use Gingerminds\MediaManagerBundle\File\MimeTypePatterns;
use Gingerminds\MediaManagerBundle\File\Reference\FileReferenceRegistry;
use Gingerminds\MediaManagerBundle\File\Reference\FileUsage;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * The library browser (`view files`), routed under the `file` resource: the management page and
 * the picker modal of the FilePickerType fields render the same `gm-file-browser` Turbo frame,
 * its state in the query (folder, filters, page, open file, dialog). The writes are
 * FileLibraryActionController's.
 */
#[IsGranted(ResourceVoter::VIEW, subject: new Expression('"file"'))]
class FileLibraryController extends AbstractFileLibraryController
{
    public const array SORTS = ['name_asc', 'name_desc', 'date_desc', 'date_asc', 'size_desc', 'size_asc'];

    /**
     * Dialogs (modals) of the browser, the picker only creates folders.
     */
    public const array DIALOGS = ['mkdir', 'rename', 'move', 'delete'];

    /**
     * @param list<string> $allowedMimes
     */
    public function __construct(
        FileLibrary $library,
        FileRepository $files,
        FileLibraryPresenter $presenter,
        protected readonly FileReferenceRegistry $references,
        protected readonly LibraryStartPathProviderInterface $startPathProvider,
        protected readonly int $maxUploadSize,
        protected readonly array $allowedMimes,
        protected readonly DisplayTimezone $displayTimezone = new DisplayTimezone(),
    ) {
        parent::__construct($library, $files, $presenter);
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('@GingermindsMediaManager/pages/file/index.html.twig', [
            'resource' => $this->getResourceRegistry()->get(self::RESOURCE),
            'browser' => $this->browser($request, false),
        ]);
    }

    /**
     * The modal of the FilePickerType fields (`accept[]`, `multiple`).
     */
    #[Route('/picker', name: 'picker', methods: ['GET'])]
    public function picker(Request $request): Response
    {
        return $this->render('@GingermindsMediaManager/components/file/_picker_modal.html.twig', [
            'browser' => $this->browser($request, true),
        ]);
    }

    /**
     * The subfolders of `node`, a lazy frame of the folder tree (navigation, or `chooser` radios).
     */
    #[Route('/tree', name: 'tree', methods: ['GET'])]
    public function tree(Request $request): Response
    {
        $picker = $request->query->getBoolean('picker');
        $node = $request->query->getString('node');

        try {
            $children = $this->library->directories($node);
        } catch (TranslatableExceptionInterface) {
            $children = [];
        }

        return $this->render('@GingermindsMediaManager/components/file/_tree_frame.html.twig', [
            'browser' => [
                'route' => $this->route($picker),
                'state' => $this->state($request, $picker),
                'picker' => $picker,
                'tree' => [],
                'branches' => $this->branches($children),
            ],
            'node' => $node,
            'children' => $children,
            'chooser' => $request->query->getBoolean('chooser'),
        ]);
    }

    /**
     * The template variables of the browser.
     *
     * @return array<string, mixed>
     */
    protected function browser(Request $request, bool $picker): array
    {
        $state = $this->state($request, $picker);

        try {
            $page = $this->library->files($this->libraryQuery($state));
        } catch (TranslatableExceptionInterface $exception) {
            $this->addFlash('danger', $this->transException($exception));
            $state['path'] = '';
            $page = $this->library->files($this->libraryQuery($state));
        }

        $path = $state['path'];
        $files = array_values($page->getItems());
        $canEdit = $this->isGranted(ResourceVoter::EDIT, self::RESOURCE);
        $dialog = $request->query->getString('dialog');
        $dialog = $canEdit && \in_array($dialog, $picker ? ['mkdir'] : self::DIALOGS, true) ? $dialog : null;
        $tree = $this->treeOf($path);

        return [
            'route' => $this->route($picker),
            'state' => $state,
            'picker' => $picker,
            'can_edit' => $canEdit,
            'breadcrumb' => $this->breadcrumb($path),
            'tree' => $tree,
            // The open folders of the tree already list their subfolders.
            'branches' => $this->branches(array_diff(array_merge(...array_values($tree)), array_keys($tree))),
            // Folders stay whatever the filters, to keep navigating, but a search only lists files.
            'directories' => 1 === $page->getPage() && !isset($state['search']) ? $this->library->directories($path) : [],
            'files' => $page,
            'usages' => array_map(\count(...), $this->references->usages($files)),
            'pick_data' => $picker ? array_column(array_map($this->presenter->file(...), $files), null, 'id') : [],
            'detail' => $picker ? null : $this->detail($state['file'] ?? null),
            'dialog' => $dialog,
            'selection' => null === $dialog ? null : [
                'files' => $this->findFiles($request->query->all('ids')),
                'paths' => array_values(array_filter($request->query->all('paths'), \is_string(...))),
            ],
            'types' => LibraryQuery::typesFor($state['accept'] ?? []),
            'max_upload_size' => $this->maxUploadSize * 1024,
            'allowed_mimes' => $this->allowedMimes,
            'csrf_token_id' => self::CSRF_TOKEN_ID,
            'presets' => $this->presenter->presets(),
        ];
    }

    /**
     * The query parameters of the browser, normalized: the links and forms rebuild their URLs
     * from it. `path` is always set (the start folder by default).
     *
     * @return array<string, mixed>
     */
    protected function state(Request $request, bool $picker): array
    {
        $query = $request->query;
        $type = $query->getString('type');
        $sort = $query->getString('sort');
        $file = $query->getString('file');
        $accept = $picker ? MimeTypePatterns::normalize($query->all('accept')) : [];

        return ['path' => $query->has('path') ? trim($query->getString('path'), '/') : $this->startPathProvider->getStartPath()] + array_filter([
            'search' => trim($query->getString('search')),
            'type' => \in_array($type, LibraryQuery::typesFor($accept), true) ? $type : null,
            'sort' => \in_array($sort, self::SORTS, true) && self::SORTS[0] !== $sort ? $sort : null,
            'from' => $this->date($query->getString('from')),
            'to' => $this->date($query->getString('to')),
            'recursive' => $query->getBoolean('recursive') ? 1 : null,
            'orphans' => $query->getBoolean('orphans') ? 1 : null,
            'duplicates' => $query->getBoolean('duplicates') ? 1 : null,
            'page' => $query->getInt('page') > 1 ? $query->getInt('page') : null,
            'file' => !$picker && Uuid::isValid($file) ? $file : null,
            'accept' => $accept,
            'multiple' => $picker && $query->getBoolean('multiple') ? 1 : null,
        ], static fn (mixed $value): bool => null !== $value && '' !== $value && [] !== $value);
    }

    /**
     * @param array<string, mixed> $state
     */
    protected function libraryQuery(array $state): LibraryQuery
    {
        [$sortBy, $direction] = explode('_', $state['sort'] ?? self::SORTS[0]);
        // Days of the display timezone, as UTC bounds (`created_at` is stored in UTC).
        [$createdFrom, $createdTo] = $this->displayTimezone->dayRangeToUtc($state['from'] ?? '', $state['to'] ?? '');

        return new LibraryQuery(
            directory: $state['path'],
            recursive: isset($state['recursive']),
            search: $state['search'] ?? null,
            type: $state['type'] ?? null,
            createdFrom: $createdFrom,
            createdTo: $createdTo,
            orphans: isset($state['orphans']),
            duplicates: isset($state['duplicates']),
            sortBy: $sortBy,
            sort: $direction,
            page: $state['page'] ?? 1,
            accept: $state['accept'] ?? [],
        );
    }

    /**
     * @return array{file: FileInterface, usages: list<FileUsage>, duplicates: list<FileInterface>, directories: array<string, string>}|null
     */
    protected function detail(?string $id): ?array
    {
        $file = null === $id ? null : $this->files->find($id);

        if (!$file instanceof FileInterface) {
            return null;
        }

        $duplicates = $this->duplicatesOf($file);

        return [
            'file' => $file,
            'usages' => $this->references->usages([$file])[$file->getId()] ?? [],
            'duplicates' => $duplicates,
            // Relative to the library root, by file id.
            'directories' => array_column(array_map($this->presenter->file(...), [$file, ...$duplicates]), 'directory', 'id'),
        ];
    }

    /**
     * The folders from the root to $path, each with its subfolders: the branch of the tree shown
     * open, the other folders load their subfolders when opened.
     *
     * @return array<string, list<string>>
     */
    protected function treeOf(string $path): array
    {
        $tree = ['' => $this->library->directories()];

        foreach ($this->breadcrumb($path) as $ancestor) {
            $tree[$ancestor] = $this->library->directories($ancestor);
        }

        return $tree;
    }

    /**
     * Whether each folder has subfolders: the tree only lets open those.
     *
     * @param array<string> $nodes
     *
     * @return array<string, bool>
     */
    protected function branches(array $nodes): array
    {
        $branches = [];

        foreach ($nodes as $node) {
            try {
                $branches[$node] = $this->library->hasDirectories($node);
            } catch (TranslatableExceptionInterface) {
                $branches[$node] = false;
            }
        }

        return $branches;
    }

    /**
     * "a/b/c": "a", "a/b", "a/b/c".
     *
     * @return list<string>
     */
    protected function breadcrumb(string $path): array
    {
        $breadcrumb = [];
        $current = '';

        foreach (array_filter(explode('/', $path), static fn (string $segment): bool => '' !== $segment) as $segment) {
            $breadcrumb[] = $current = ltrim($current . '/' . $segment, '/');
        }

        return $breadcrumb;
    }

    private function route(bool $picker): string
    {
        return $picker ? 'gingerminds_media_manager_file_picker' : 'gingerminds_media_manager_file_index';
    }

    private function date(string $value): ?string
    {
        return false !== \DateTimeImmutable::createFromFormat('!Y-m-d', $value) ? $value : null;
    }
}
