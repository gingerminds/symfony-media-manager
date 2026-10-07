<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Http;

use Gingerminds\MediaManagerBundle\Storage\DiskRegistry;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\String\UnicodeString;

/**
 * Streams a stored file with browser cache headers (one day, revalidated with ETag / Last-Modified).
 */
final readonly class FileResponseFactory
{
    private const int MAX_AGE = 86400;

    public function __construct(
        private DiskRegistry $disks,
    ) {
    }

    public function create(Request $request, string $disk, string $path, ?string $mimeType = null, ?string $name = null): StreamedResponse
    {
        $filesystem = $this->disks->get($disk);

        if (!$filesystem->fileExists($path)) {
            throw new NotFoundHttpException();
        }

        $lastModified = $filesystem->lastModified($path);

        $response = new StreamedResponse(static function () use ($filesystem, $path): void {
            $stream = $filesystem->readStream($path);
            fpassthru($stream);
            fclose($stream);
        });
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->setEtag(md5($path . $lastModified));
        $response->setLastModified(new \DateTimeImmutable('@' . $lastModified));

        if ($response->isNotModified($request)) {
            return $response;
        }

        $response->headers->set('Content-Type', $mimeType ?? $filesystem->mimeType($path));
        $response->headers->set('Content-Length', (string) $filesystem->fileSize($path));

        if (null !== $name) {
            $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $name,
                $this->asciiFallback($name),
            ));
        }

        return $response;
    }

    private function asciiFallback(string $name): string
    {
        $ascii = (string) preg_replace('/[^\x20-\x7E]|[%\/\\\\]/', '_', new UnicodeString($name)->ascii()->toString());

        return '' === trim($ascii, '_') ? 'file' : $ascii;
    }
}
