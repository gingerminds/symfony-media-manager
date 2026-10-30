<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\File;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Http\FileResponseFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /api/files/{id}: the original file, read by API Platform (`$data`).
 */
class ShowFileController
{
    public function __construct(
        protected readonly FileResponseFactory $responses,
    ) {
    }

    public function __invoke(Request $request, FileInterface $data): Response
    {
        return $this->responses->file($request, $data);
    }
}
