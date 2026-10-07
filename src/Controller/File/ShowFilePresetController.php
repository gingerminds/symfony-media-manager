<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\File;

use Gingerminds\MediaManagerBundle\Http\FileResponseFactory;
use Gingerminds\MediaManagerBundle\Image\ImageProcessor;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use League\Glide\Filesystem\FileNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /api/files/{id}/{preset}: the image rendered with a preset (SVG served as is).
 */
class ShowFilePresetController extends ShowFileController
{
    public function __construct(
        FileRepository $files,
        FileResponseFactory $responses,
        protected readonly ImageProcessor $images,
    ) {
        parent::__construct($files, $responses);
    }

    public function __invoke(Request $request, string $id, string $preset = ''): Response
    {
        $file = $this->find($id);

        if (!$file->isImage()) {
            throw new BadRequestHttpException('This file is not an image.');
        }

        if (!$this->images->hasPreset($preset)) {
            throw new NotFoundHttpException('Unknown preset.');
        }

        if (!$this->images->supports($file)) {
            return $this->responses->create($request, $file->getDisk(), $file->getPath(), $file->getMimeType());
        }

        try {
            $path = $this->images->process($file, $preset);
        } catch (FileNotFoundException $exception) {
            throw new NotFoundHttpException('Image not found.', $exception);
        }

        return $this->responses->create($request, $file->getDisk(), $path);
    }
}
