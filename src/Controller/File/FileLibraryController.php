<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\File;

use Gingerminds\CoreBundle\Controller\CrudContext;
use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\File\FileLibraryPresenter;
use Gingerminds\MediaManagerBundle\File\LibraryQuery;
use Gingerminds\MediaManagerBundle\File\LibraryStartPathProviderInterface;
use Gingerminds\MediaManagerBundle\File\MimeTypePatterns;
use Gingerminds\MediaManagerBundle\File\Reference\FileReferenceRegistry;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Library management page and the read endpoints of the library browser (`view files`).
 */
class FileLibraryController extends AbstractFileLibraryController
{
    /**
     * Replaced by a file id in the `show` url of the browser.
     */
    public const string ID_PLACEHOLDER = '00000000-0000-7000-8000-000000000000';

    /**
     * @param list<string> $allowedMimes
     */
    public function __construct(
        CrudContext $context,
        FileLibrary $library,
        FileRepository $files,
        FileLibraryPresenter $presenter,
        protected readonly FileReferenceRegistry $references,
        protected readonly LibraryStartPathProviderInterface $startPathProvider,
        protected readonly int $maxUploadSize,
        protected readonly array $allowedMimes,
    ) {
        parent::__construct($context, $library, $files, $presenter);
    }

    public function index(): Response
    {
        $this->denyAccessUnlessGranted(AbstractResourceVoter::VIEW, self::RESOURCE);

        return $this->render('@GingermindsMediaManager/pages/file/index.html.twig', [
            'resource' => $this->context->resources->get(self::RESOURCE),
            'browser' => $this->browserParameters(),
        ]);
    }

    /**
     * The library browser in a modal, to pick files for the FilePickerType fields of a form.
     */
    public function picker(): Response
    {
        $this->denyAccessUnlessGranted(AbstractResourceVoter::VIEW, self::RESOURCE);

        return $this->render('@GingermindsMediaManager/components/file/_picker_modal.html.twig', [
            'browser' => $this->browserParameters(),
        ]);
    }

    public function browse(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AbstractResourceVoter::VIEW, self::RESOURCE);

        return $this->attempt(function () use ($request): JsonResponse {
            $query = $this->libraryQuery($request);
            $page = $this->library->files($query);

            return new JsonResponse($this->presenter->browse($query->directory, $this->directoryList($query->directory), $page));
        });
    }

    public function directories(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AbstractResourceVoter::VIEW, self::RESOURCE);

        return $this->attempt(fn (): JsonResponse => new JsonResponse($this->directoryList($request->query->getString('path'))));
    }

    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(AbstractResourceVoter::VIEW, self::RESOURCE);
        $file = $this->findFile($id);
        $usages = $this->references->usages([$file])[$file->getId()] ?? [];

        return new JsonResponse([
            ...$this->presenter->file($file, \count($usages)),
            'usageList' => $this->presenter->usages($usages),
            'duplicates' => array_map(fn (FileInterface $duplicate): array => $this->presenter->file($duplicate), $this->duplicatesOf($file)),
        ]);
    }

    /**
     * Data attributes of the browser (gm-file-browser Stimulus controller).
     *
     * @return array<string, mixed>
     */
    protected function browserParameters(): array
    {
        $urlGenerator = $this->context->urlGenerator;

        return [
            'startPath' => $this->startPathProvider->getStartPath(),
            'canEdit' => $this->isGranted(AbstractResourceVoter::EDIT, self::RESOURCE),
            'csrfToken' => $this->context->csrfTokenManager->getToken(self::CSRF_TOKEN_ID)->getValue(),
            'maxUploadSize' => $this->maxUploadSize * 1024,
            'allowedMimes' => $this->allowedMimes,
            'types' => array_keys(LibraryQuery::TYPES),
            'idPlaceholder' => self::ID_PLACEHOLDER,
            'urls' => [
                'browse' => $urlGenerator->generate('gingerminds_media_manager_file_browse'),
                'directories' => $urlGenerator->generate('gingerminds_media_manager_file_directories'),
                'directory' => $urlGenerator->generate('gingerminds_media_manager_file_directory_create'),
                'show' => $urlGenerator->generate('gingerminds_media_manager_file_show', ['id' => self::ID_PLACEHOLDER]),
                'upload' => $urlGenerator->generate('gingerminds_media_manager_file_upload'),
                'move' => $urlGenerator->generate('gingerminds_media_manager_file_move'),
                'delete' => $urlGenerator->generate('gingerminds_media_manager_file_delete'),
                'merge' => $urlGenerator->generate('gingerminds_media_manager_file_merge'),
            ],
        ];
    }

    protected function libraryQuery(Request $request): LibraryQuery
    {
        $query = $request->query;
        $type = $query->getString('type');
        $sort = $query->getString('sort', 'name');

        return new LibraryQuery(
            directory: $query->getString('path'),
            recursive: $query->getBoolean('recursive'),
            search: $query->getString('search') ?: null,
            type: isset(LibraryQuery::TYPES[$type]) ? $type : null,
            createdFrom: $this->date($query->getString('from')),
            createdTo: $this->date($query->getString('to'))?->setTime(23, 59, 59),
            orphans: $query->getBoolean('orphans'),
            duplicates: $query->getBoolean('duplicates'),
            sortBy: isset(LibraryQuery::SORTS[$sort]) ? $sort : 'name',
            sort: 'desc' === $query->getString('direction') ? 'desc' : 'asc',
            page: max(1, $query->getInt('page', 1)),
            accept: MimeTypePatterns::normalize($query->all('accept')),
        );
    }

    /**
     * Subdirectories, each telling whether it has its own (the tree only shows a caret then).
     *
     * @return list<array{name: string, path: string, hasChildren?: bool}>
     */
    private function directoryList(string $path): array
    {
        return array_map(
            fn (string $directory): array => $this->presenter->directory($directory, $this->library->hasDirectories($directory)),
            $this->library->directories($path),
        );
    }

    private function date(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $date ? null : $date;
    }
}
