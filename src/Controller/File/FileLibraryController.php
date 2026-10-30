<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\File;

use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Gingerminds\CoreBundle\Service\DisplayTimezone;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\File\FileLibraryPresenter;
use Gingerminds\MediaManagerBundle\File\LibraryQuery;
use Gingerminds\MediaManagerBundle\File\LibraryStartPathProviderInterface;
use Gingerminds\MediaManagerBundle\File\MimeTypePatterns;
use Gingerminds\MediaManagerBundle\File\Reference\FileReferenceRegistry;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Library management page and the read endpoints of the library browser (`view files`), routed
 * under the `file` resource.
 */
#[IsGranted(ResourceVoter::VIEW, subject: new Expression('"file"'))]
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
    public function index(): Response
    {
        return $this->render('@GingermindsMediaManager/pages/file/index.html.twig', [
            'resource' => $this->getResourceRegistry()->get(self::RESOURCE),
            'browser' => $this->browserParameters(),
        ]);
    }

    /**
     * The library browser in a modal, to pick files for the FilePickerType fields of a form.
     */
    #[Route('/picker', name: 'picker', methods: ['GET'])]
    public function picker(): Response
    {
        return $this->render('@GingermindsMediaManager/components/file/_picker_modal.html.twig', [
            'browser' => $this->browserParameters(),
        ]);
    }

    #[Route('/browse', name: 'browse', methods: ['GET'])]
    public function browse(Request $request): JsonResponse
    {
        return $this->attempt(function () use ($request): JsonResponse {
            $query = $this->libraryQuery($request);
            $page = $this->library->files($query);

            return new JsonResponse($this->presenter->browse($query->directory, $this->directoryList($query->directory), $page));
        });
    }

    #[Route('/directories', name: 'directories', methods: ['GET'])]
    public function directories(Request $request): JsonResponse
    {
        return $this->attempt(fn (): JsonResponse => new JsonResponse($this->directoryList($request->query->getString('path'))));
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
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
        $csrfTokenManager = $this->container->get('security.csrf.token_manager');
        \assert($csrfTokenManager instanceof CsrfTokenManagerInterface);

        return [
            'startPath' => $this->startPathProvider->getStartPath(),
            'canEdit' => $this->isGranted(ResourceVoter::EDIT, self::RESOURCE),
            'csrfToken' => $csrfTokenManager->getToken(self::CSRF_TOKEN_ID)->getValue(),
            'maxUploadSize' => $this->maxUploadSize * 1024,
            'allowedMimes' => $this->allowedMimes,
            'types' => array_keys(LibraryQuery::TYPES),
            'idPlaceholder' => self::ID_PLACEHOLDER,
            'urls' => [
                'browse' => $this->generateUrl('gingerminds_media_manager_file_browse'),
                'directories' => $this->generateUrl('gingerminds_media_manager_file_directories'),
                'directory' => $this->generateUrl('gingerminds_media_manager_file_directory_create'),
                'show' => $this->generateUrl('gingerminds_media_manager_file_show', ['id' => self::ID_PLACEHOLDER]),
                'upload' => $this->generateUrl('gingerminds_media_manager_file_upload'),
                'move' => $this->generateUrl('gingerminds_media_manager_file_move'),
                'delete' => $this->generateUrl('gingerminds_media_manager_file_delete'),
                'merge' => $this->generateUrl('gingerminds_media_manager_file_merge'),
            ],
        ];
    }

    protected function libraryQuery(Request $request): LibraryQuery
    {
        $query = $request->query;
        $type = $query->getString('type');
        $sort = $query->getString('sort', 'name');
        // Days of the display timezone, as UTC bounds (`created_at` is stored in UTC).
        [$createdFrom, $createdTo] = $this->displayTimezone->dayRangeToUtc($query->getString('from'), $query->getString('to'));

        return new LibraryQuery(
            directory: $query->getString('path'),
            recursive: $query->getBoolean('recursive'),
            search: $query->getString('search') ?: null,
            type: isset(LibraryQuery::TYPES[$type]) ? $type : null,
            createdFrom: $createdFrom,
            createdTo: $createdTo,
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
}
