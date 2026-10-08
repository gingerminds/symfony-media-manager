<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\File;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Http\FileResponseFactory;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * GET /api/files/{id}: the original file.
 */
class ShowFileController
{
    public function __construct(
        protected readonly FileRepository $files,
        protected readonly FileResponseFactory $responses,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $file = $this->find($id);

        return $this->responses->create($request, $file->getDisk(), $file->getPath(), $file->getMimeType(), $file->getOriginalName());
    }

    protected function find(string $id): FileInterface
    {
        $file = Uuid::isValid($id) ? $this->files->find($id) : null;

        if (!$file instanceof FileInterface) {
            throw new NotFoundHttpException();
        }

        return $file;
    }
}
