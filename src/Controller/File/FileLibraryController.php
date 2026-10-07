<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\File;

use Gingerminds\CoreBundle\Controller\ControllerTrait;
use Gingerminds\CoreBundle\Controller\CrudContext;
use Gingerminds\CoreBundle\Security\Voter\AbstractResourceVoter;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Exception\TranslatableExceptionInterface;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\File\FileLibraryPresenter;
use Gingerminds\MediaManagerBundle\File\LibraryQuery;
use Gingerminds\MediaManagerBundle\File\LibraryStartPathProviderInterface;
use Gingerminds\MediaManagerBundle\File\Reference\FileReferenceRegistry;
use Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Library management page and the JSON endpoints of the library browser.
 */
class FileLibraryController
{
    use ControllerTrait;

    public const string CSRF_TOKEN_ID = 'gm-file-library';

    /**
     * Replaced by a file id in the `show` url of the browser.
     */
    public const string ID_PLACEHOLDER = '00000000-0000-7000-8000-000000000000';

    private const string RESOURCE = 'file';

    /**
     * @param list<string> $allowedMimes
     */
    public function __construct(
        protected readonly CrudContext $context,
        protected readonly FileLibrary $library,
        protected readonly FileRepository $files,
        protected readonly FileReferenceRegistry $references,
        protected readonly FileLibraryPresenter $presenter,
        protected readonly LibraryStartPathProviderInterface $startPathProvider,
        protected readonly int $maxUploadSize,
        protected readonly array $allowedMimes,
    ) {
    }

    public function index(): Response
    {
        $this->denyAccessUnlessGranted(AbstractResourceVoter::VIEW, self::RESOURCE);

        return $this->render('@GingermindsMediaManager/pages/file/index.html.twig', [
            'resource' => $this->context->resources->get(self::RESOURCE),
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

    public function upload(Request $request): JsonResponse
    {
        $this->denyUnlessCanEdit($request);
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->error($this->trans('error.upload_failed', [], GingermindsMediaManagerBundle::TRANSLATION_DOMAIN));
        }

        return $this->attempt(function () use ($request, $file): JsonResponse {
            $result = $this->library->upload($file, $request->request->getString('path'));

            return new JsonResponse(
                ['file' => $this->presenter->file($result->file), 'duplicate' => $result->duplicate],
                $result->duplicate ? Response::HTTP_OK : Response::HTTP_CREATED,
            );
        });
    }

    public function createDirectory(Request $request): JsonResponse
    {
        $this->denyUnlessCanEdit($request);
        $payload = $request->getPayload();

        return $this->attempt(fn (): JsonResponse => new JsonResponse(
            $this->presenter->directory($this->library->mkdir($payload->getString('parent'), $payload->getString('name'))),
            Response::HTTP_CREATED,
        ));
    }

    public function deleteDirectory(Request $request): JsonResponse
    {
        $this->denyUnlessCanEdit($request);

        return $this->attempt(function () use ($request): JsonResponse {
            $this->library->rmdir($request->getPayload()->getString('path'));

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        });
    }

    public function rename(Request $request, string $id): JsonResponse
    {
        $this->denyUnlessCanEdit($request);
        $file = $this->findFile($id);

        return $this->attempt(function () use ($request, $file): JsonResponse {
            $this->library->rename($file, $request->getPayload()->getString('name'));

            return new JsonResponse($this->presenter->file($file));
        });
    }

    public function move(Request $request): JsonResponse
    {
        $this->denyUnlessCanEdit($request);
        $payload = $request->getPayload();
        $files = $this->findFiles($payload->all('ids'));

        return $this->attempt(function () use ($payload, $files): JsonResponse {
            $this->library->move($files, $payload->getString('path'));

            return new JsonResponse(array_map(fn (FileInterface $file): array => $this->presenter->file($file), $files));
        });
    }

    /**
     * Used files are kept and returned with their usages; 422 when none could be deleted.
     */
    public function delete(Request $request): JsonResponse
    {
        $this->denyUnlessCanEdit($request);
        $files = $this->findFiles($request->getPayload()->all('ids'));
        $ids = array_map(static fn (FileInterface $file): string => $file->getId(), $files);
        $result = $this->library->delete($files);

        return new JsonResponse(
            [
                'deleted' => array_values(array_diff($ids, array_map(static fn (array $blocked): string => $blocked['file']->getId(), $result->blocked))),
                'blocked' => array_map(fn (array $blocked): array => [
                    ...$this->presenter->file($blocked['file'], \count($blocked['usages'])),
                    'usageList' => $this->presenter->usages($blocked['usages']),
                ], $result->blocked),
            ],
            [] === $result->deleted && [] !== $result->blocked ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
        );
    }

    /**
     * Every reference to the duplicates (all the files with the same content without `ids`) now
     * points to the kept file.
     */
    public function merge(Request $request): JsonResponse
    {
        $this->denyUnlessCanEdit($request);
        $payload = $request->getPayload();
        $keep = $this->findFile($payload->getString('keep'));
        $duplicates = $payload->has('ids') ? $this->findFiles($payload->all('ids')) : $this->duplicatesOf($keep);

        try {
            $updated = $this->library->mergeDuplicates($keep, $duplicates);
        } catch (\InvalidArgumentException) {
            return $this->error($this->trans('error.not_duplicates', [], GingermindsMediaManagerBundle::TRANSLATION_DOMAIN));
        }

        return new JsonResponse(['file' => $this->presenter->file($keep), 'updated' => $updated]);
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
        );
    }

    /**
     * Library errors (missing directory, upload refused...) as a 422 with a translated message.
     *
     * @param callable(): JsonResponse $action
     */
    private function attempt(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (TranslatableExceptionInterface $exception) {
            return $this->error($exception->trans($this->context->translator));
        }
    }

    private function error(string $message): JsonResponse
    {
        return new JsonResponse(['error' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function denyUnlessCanEdit(Request $request): void
    {
        $this->denyAccessUnlessGranted(AbstractResourceVoter::EDIT, self::RESOURCE);

        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->headers->get('X-CSRF-Token'))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
    }

    private function findFile(string $id): FileInterface
    {
        $file = Uuid::isValid($id) ? $this->files->find($id) : null;

        if (!$file instanceof FileInterface) {
            throw new NotFoundHttpException('File not found.');
        }

        return $file;
    }

    /**
     * Unknown ids are ignored.
     *
     * @param array<mixed> $ids
     *
     * @return list<FileInterface>
     */
    private function findFiles(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (mixed $id): bool => \is_string($id) && Uuid::isValid($id))));

        return [] === $ids ? [] : array_values($this->files->findBy(['id' => $ids]));
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

    /**
     * @return list<FileInterface>
     */
    private function duplicatesOf(FileInterface $file): array
    {
        if (null === $file->getHash()) {
            return [];
        }

        return array_values(array_filter(
            $this->files->findBy(['hash' => $file->getHash()], ['createdAt' => 'ASC']),
            static fn (FileInterface $duplicate): bool => $duplicate->getId() !== $file->getId(),
        ));
    }

    private function date(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $date ? null : $date;
    }
}
