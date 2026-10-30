<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\File;

use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Gingerminds\MediaManagerBundle\Controller\File\Input\DirectoryInput;
use Gingerminds\MediaManagerBundle\Controller\File\Input\FileInput;
use Gingerminds\MediaManagerBundle\Controller\File\Input\SelectionInput;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Exception\TranslatableExceptionInterface;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\File\FileLibraryPresenter;
use Gingerminds\MediaManagerBundle\File\LibraryDirectories;
use Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The library writes (`edit files`): forms of the browser, posted with its CSRF token, then back
 * to the browser (`return`) with a flash message. The upload answers JSON (gm-file-upload).
 */
#[Route('/files', name: 'gingerminds_media_manager_file_')]
#[IsGranted(ResourceVoter::EDIT, subject: new Expression('"file"'))]
#[IsCsrfTokenValid(self::CSRF_TOKEN_ID)]
class FileLibraryActionController extends AbstractFileLibraryController
{
    public function __construct(
        FileLibrary $library,
        LibraryDirectories $directories,
        FileRepository $files,
        FileLibraryPresenter $presenter,
        protected readonly int $maxDirectoryMove,
    ) {
        parent::__construct($library, $directories, $files, $presenter);
    }

    /**
     * One file a request (`file`, `path`): its row, `duplicate` when the content was already in the library.
     */
    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->uploadError($this->trans('error.upload_failed', [], GingermindsMediaManagerBundle::TRANSLATION_DOMAIN));
        }

        try {
            $result = $this->library->upload($file, $request->request->getString('path'));
        } catch (TranslatableExceptionInterface $exception) {
            return $this->uploadError($file->getClientOriginalName() . ' : ' . $this->transException($exception));
        }

        $name = $file->getClientOriginalName();
        $existing = ltrim($this->presenter->file($result->file)['directory'] . '/' . $result->file->getOriginalName(), '/');
        $this->addFlash(
            $result->duplicate ? 'info' : 'success',
            $result->duplicate ? $this->message('duplicate', ['%name%' => $name, '%existing%' => $existing]) : $this->message('uploaded', ['%name%' => $name]),
        );

        return new JsonResponse(
            ['file' => $this->presenter->file($result->file), 'duplicate' => $result->duplicate],
            $result->duplicate ? Response::HTTP_OK : Response::HTTP_CREATED,
        );
    }

    #[Route('/directories', name: 'directory_create', methods: ['POST'])]
    public function createDirectory(Request $request, #[MapRequestPayload] DirectoryInput $input): RedirectResponse
    {
        return $this->attempt($request, function () use ($input): void {
            $this->directories->mkdir($input->parent, $input->name);
        });
    }

    #[Route('/directories/rename', name: 'directory_rename', methods: ['POST'])]
    public function renameDirectory(Request $request, #[MapRequestPayload] DirectoryInput $input): RedirectResponse
    {
        return $this->attempt($request, function () use ($input): void {
            $parent = \dirname($input->path);
            $path = $this->directories->moveDirectory($input->path, '.' === $parent ? '' : $parent, $input->name, $this->maxDirectoryMove);
            $this->addFlash('success', $this->message('directory_renamed', ['%name%' => basename($path)]));
        });
    }

    /**
     * The files and folders of the selection go into `target`.
     */
    #[Route('/move', name: 'move', methods: ['POST'])]
    public function move(Request $request, #[MapRequestPayload] SelectionInput $input): RedirectResponse
    {
        return $this->attempt($request, function () use ($input): void {
            $files = $this->findFiles($input->ids);

            if ([] !== $files) {
                $this->library->move($files, $input->target);
                $this->addFlash('success', $this->message('moved', ['%count%' => \count($files)]));
            }

            if ([] !== $input->paths) {
                $moved = $this->directories->moveDirectories($input->paths, $input->target, null, $this->maxDirectoryMove);
                $target = '' === $input->target ? $this->trans('file.root', [], GingermindsMediaManagerBundle::TRANSLATION_DOMAIN) : $input->target;
                $this->addFlash('success', $this->message('directories_moved', ['%count%' => \count($moved), '%path%' => $target]));
            }
        });
    }

    /**
     * Used files and folders not empty are kept, and listed.
     */
    #[Route('/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, #[MapRequestPayload] SelectionInput $input): RedirectResponse
    {
        return $this->attempt($request, function () use ($input): void {
            $files = $this->findFiles($input->ids);

            if ([] !== $files) {
                $result = $this->library->delete($files);
                $this->flashIf('success', $result->deleted, $this->message('deleted', ['%count%' => \count($result->deleted)]));
                $this->flashIf('warning', $result->blocked, $this->message('blocked') . ' ' . implode(', ', array_map(
                    static fn (array $blocked): string => $blocked['file']->getOriginalName() . ' (' . \count($blocked['usages']) . ')',
                    $result->blocked,
                )));
            }

            if ([] !== $input->paths) {
                $result = $this->directories->rmdirs($input->paths);
                $this->flashIf('success', $result['deleted'], $this->message('directories_deleted', ['%count%' => \count($result['deleted'])]));
                $this->flashIf('warning', $result['kept'], $this->message('directories_kept') . ' ' . implode(', ', $result['kept']));
            }
        });
    }

    /**
     * Only the sent fields (`name`, `alt`) are updated.
     */
    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function update(Request $request, string $id, #[MapRequestPayload] FileInput $input): RedirectResponse
    {
        $file = $this->findFiles([$id])[0] ?? throw $this->createNotFoundException('File not found.');

        return $this->attempt($request, function () use ($file, $input): void {
            if (null !== $input->alt) {
                $this->library->setAlt($file, $input->alt);
                $this->addFlash('success', $this->message('alt_saved'));
            }

            if (null !== $input->name) {
                $this->library->rename($file, $input->name);
            }
        });
    }

    /**
     * Every reference to the duplicates of `keep` now points to it, the duplicates are deleted.
     */
    #[Route('/merge', name: 'merge', methods: ['POST'])]
    public function merge(Request $request): RedirectResponse
    {
        return $this->attempt($request, function () use ($request): void {
            $keep = $this->findFiles([$request->request->getString('keep')])[0] ?? null;

            if (!$keep instanceof FileInterface) {
                return;
            }

            try {
                $updated = $this->library->mergeDuplicates($keep, $this->duplicatesOf($keep));
            } catch (\InvalidArgumentException) {
                $this->addFlash('danger', $this->trans('error.not_duplicates', [], GingermindsMediaManagerBundle::TRANSLATION_DOMAIN));

                return;
            }

            $this->addFlash('success', $this->message('merged', ['%count%' => $updated]));
        });
    }

    /**
     * Runs a write, a library error being a flash, then back to the browser.
     *
     * @param callable(): void $action
     */
    protected function attempt(Request $request, callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (TranslatableExceptionInterface $exception) {
            $this->addFlash('danger', $this->transException($exception));
        }

        // The browser URL the form was posted from, else the library page.
        $index = $this->generateUrl('gingerminds_media_manager_file_index');
        $return = $request->request->getString('return');

        return $this->redirect(str_starts_with($return, $index) ? $return : $index, Response::HTTP_SEE_OTHER);
    }

    private function uploadError(string $message): JsonResponse
    {
        $this->addFlash('danger', $message);

        return new JsonResponse(['error' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @param array<mixed> $items
     */
    private function flashIf(string $type, array $items, string $message): void
    {
        if ([] !== $items) {
            $this->addFlash($type, $message);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function message(string $key, array $parameters = []): string
    {
        return $this->trans('file.message.' . $key, $parameters, GingermindsMediaManagerBundle::TRANSLATION_DOMAIN);
    }
}
