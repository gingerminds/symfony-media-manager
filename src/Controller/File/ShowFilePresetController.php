<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Controller\File;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Http\FileResponseFactory;
use Gingerminds\MediaManagerBundle\Image\ImageProcessor;
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
        FileResponseFactory $responses,
        protected readonly ImageProcessor $images,
    ) {
        parent::__construct($responses);
    }

    public function __invoke(Request $request, FileInterface $data, string $preset = ''): Response
    {
        $file = $data;

        if (!$file->isImage()) {
            throw new BadRequestHttpException('This file is not an image.');
        }

        if (!$this->images->hasPreset($preset)) {
            throw new NotFoundHttpException('Unknown preset.');
        }

        if (!$this->images->supports($file)) {
            return $this->responses->file($request, $file);
        }

        return $this->responses->preset($request, $file, $preset, $this->images->parameters($preset), function () use ($file, $preset): string {
            try {
                return $this->images->process($file, $preset);
            } catch (FileNotFoundException $exception) {
                throw new NotFoundHttpException('Image not found.', $exception);
            }
        });
    }
}
