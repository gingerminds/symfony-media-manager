<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\File;

use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\File\FileLibraryPresenter;
use Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Write endpoints of the library browser: `edit files` and the CSRF token in the `X-CSRF-Token` header.
 */
#[Route('/files', name: 'gingerminds_media_manager_file_')]
#[IsGranted(ResourceVoter::EDIT, subject: new Expression('"file"'))]
#[IsCsrfTokenValid(self::CSRF_TOKEN_ID, tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
class FileLibraryActionController extends AbstractFileLibraryController
{
    public function __construct(
        FileLibrary $library,
        FileRepository $files,
        FileLibraryPresenter $presenter,
        protected readonly int $maxDirectoryMove,
    ) {
        parent::__construct($library, $files, $presenter);
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
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

    #[Route('/directories', name: 'directory_create', methods: ['POST'])]
    public function createDirectory(Request $request): JsonResponse
    {
        $payload = $request->getPayload();

        return $this->attempt(fn (): JsonResponse => new JsonResponse(
            $this->presenter->directory($this->library->mkdir($payload->getString('parent'), $payload->getString('name'))),
            Response::HTTP_CREATED,
        ));
    }

    /**
     * Empty directories only: the others are kept and returned; 422 when none could be deleted.
     */
    #[Route('/directories', name: 'directory_delete', methods: ['DELETE'])]
    public function deleteDirectory(Request $request): JsonResponse
    {
        return $this->attempt(function () use ($request): JsonResponse {
            $result = $this->library->rmdirs($this->directoryPaths($request));

            return new JsonResponse(
                $result,
                [] === $result['deleted'] && [] !== $result['kept'] ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
            );
        });
    }

    /**
     * Moves `paths` under `parent`; with one path, `name` renames it.
     */
    #[Route('/directories', name: 'directory_move', methods: ['PATCH'])]
    public function moveDirectory(Request $request): JsonResponse
    {
        $payload = $request->getPayload();
        $paths = $this->directoryPaths($request);
        $name = 1 === \count($paths) ? $payload->getString('name') : '';

        return $this->attempt(fn (): JsonResponse => new JsonResponse(array_map(
            $this->presenter->directory(...),
            $this->library->moveDirectories($paths, $payload->getString('parent'), '' === $name ? null : $name, $this->maxDirectoryMove),
        )));
    }

    /**
     * Only the fields of the payload (`name`, `alt`) are updated.
     */
    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function update(Request $request, string $id): JsonResponse
    {
        $file = $this->findFile($id);
        $payload = $request->getPayload();

        return $this->attempt(function () use ($payload, $file): JsonResponse {
            if ($payload->has('alt')) {
                $this->library->setAlt($file, $payload->getString('alt'));
            }

            if ($payload->has('name')) {
                $this->library->rename($file, $payload->getString('name'));
            }

            return new JsonResponse($this->presenter->file($file));
        });
    }

    #[Route('/move', name: 'move', methods: ['POST'])]
    public function move(Request $request): JsonResponse
    {
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
    #[Route('/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request): JsonResponse
    {
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
    #[Route('/merge', name: 'merge', methods: ['POST'])]
    public function merge(Request $request): JsonResponse
    {
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
     * @return list<string>
     */
    private function directoryPaths(Request $request): array
    {
        return array_values(array_unique(array_filter($request->getPayload()->all('paths'), \is_string(...))));
    }
}
