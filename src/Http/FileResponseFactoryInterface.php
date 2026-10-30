<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Http;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The responses of `GET /api/files/{id}` and `/{preset}`. Alias it to your service to serve the
 * files otherwise (X-Sendfile, a redirection to a signed bucket URL...).
 */
interface FileResponseFactoryInterface
{
    public function file(Request $request, FileInterface $file): Response;

    /**
     * @param array<string, mixed> $parameters the preset definition
     * @param \Closure(): string   $render     renders the image, its path on the disk of the file
     */
    public function preset(Request $request, FileInterface $file, string $preset, array $parameters, \Closure $render): Response;
}
