<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\File;

use Gingerminds\CoreBundle\Controller\ControllerTrait;
use Gingerminds\CoreBundle\Controller\CrudContext;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Exception\TranslatableExceptionInterface;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\File\FileLibraryPresenter;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Shared by the library page (reads) and the library actions (writes).
 */
abstract class AbstractFileLibraryController
{
    use ControllerTrait;

    public const string CSRF_TOKEN_ID = 'gm-file-library';

    protected const string RESOURCE = 'file';

    public function __construct(
        protected readonly CrudContext $context,
        protected readonly FileLibrary $library,
        protected readonly FileRepository $files,
        protected readonly FileLibraryPresenter $presenter,
    ) {
    }

    /**
     * Library errors (missing directory, upload refused...) as a 422 with a translated message.
     *
     * @param callable(): JsonResponse $action
     */
    protected function attempt(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (TranslatableExceptionInterface $exception) {
            return $this->error($exception->trans($this->context->translator));
        }
    }

    protected function error(string $message): JsonResponse
    {
        return new JsonResponse(['error' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    protected function findFile(string $id): FileInterface
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
    protected function findFiles(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (mixed $id): bool => \is_string($id) && Uuid::isValid($id))));

        return [] === $ids ? [] : array_values($this->files->findBy(['id' => $ids]));
    }

    /**
     * @return list<FileInterface>
     */
    protected function duplicatesOf(FileInterface $file): array
    {
        if (null === $file->getHash()) {
            return [];
        }

        return array_values(array_filter(
            $this->files->findBy(['hash' => $file->getHash()], ['createdAt' => 'ASC']),
            static fn (FileInterface $duplicate): bool => $duplicate->getId() !== $file->getId(),
        ));
    }
}
