<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Http;

use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Storage\DiskRegistry;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\String\UnicodeString;

/**
 * Streams a stored file with browser cache headers (one day, revalidated with ETag / Last-Modified)
 * and the security headers of FileResponseHeaders, 304 included. The cache headers come from the
 * row (hash, dates): a 304 reads nothing on the disk, a 200 only opens the file.
 */
final readonly class FileResponseFactory
{
    private const int MAX_AGE = 86400;

    public function __construct(
        private DiskRegistry $disks,
    ) {
    }

    /**
     * The original file, inline under its original name.
     */
    public function file(Request $request, FileInterface $file): StreamedResponse
    {
        $response = $this->cacheable($file, $this->etag($file), $file->getMimeType());

        if ($response->isNotModified($request)) {
            return $response;
        }

        $this->stream($response, $file->getDisk(), $file->getPath());
        $response->headers->set('Content-Type', $file->getMimeType());
        $response->headers->set('Content-Length', (string) $file->getSize());
        $name = strtr($file->getOriginalName(), '/\\', '__');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $name, $this->asciiFallback($name)));

        return $response;
    }

    /**
     * An image rendered with a preset, rendered (`$render`: its path on the disk of the file) only
     * when the browser does not have it already.
     *
     * @param array<string, mixed> $parameters the preset definition, part of the ETag
     * @param \Closure(): string   $render
     */
    public function preset(Request $request, FileInterface $file, string $preset, array $parameters, \Closure $render): StreamedResponse
    {
        $response = $this->cacheable($file, md5($this->etag($file) . $preset . serialize($parameters)), null);

        if ($response->isNotModified($request)) {
            return $response;
        }

        $path = $render();
        // The format of the preset (`fm`, `pjpg` being a progressive JPEG), else the one of the rendered file.
        $format = \is_string($parameters['fm'] ?? null) ? str_replace('pjpg', 'jpg', $parameters['fm']) : '';
        $mimeType = MimeTypes::getDefault()->getMimeTypes($format)[0] ?? $this->disks->get($file->getDisk())->mimeType($path);
        FileResponseHeaders::apply($response, $mimeType);
        $this->stream($response, $file->getDisk(), $path);
        $response->headers->set('Content-Type', $mimeType);

        return $response;
    }

    private function cacheable(FileInterface $file, string $etag, ?string $mimeType): StreamedResponse
    {
        $response = new StreamedResponse();
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->setEtag($etag);

        if ($file instanceof TimestampableInterface && ($date = $file->getUpdatedAt() ?? $file->getCreatedAt()) instanceof \DateTimeImmutable) {
            $response->setLastModified($date);
        }

        FileResponseHeaders::apply($response, $mimeType);

        return $response;
    }

    /**
     * The content, else the row: a file without hash (imported) changes with its row.
     */
    private function etag(FileInterface $file): string
    {
        $date = $file instanceof TimestampableInterface ? ($file->getUpdatedAt() ?? $file->getCreatedAt()) : null;

        return $file->getHash() ?? md5($file->getId() . $date?->format('U.u'));
    }

    /**
     * Opened before the response is sent: a missing file is a 404, not an empty 200.
     */
    private function stream(StreamedResponse $response, string $disk, string $path): void
    {
        try {
            $stream = $this->disks->get($disk)->readStream($path);
        } catch (FilesystemException $exception) {
            throw new NotFoundHttpException('File not found.', $exception);
        }

        $response->setCallback(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        });
    }

    private function asciiFallback(string $name): string
    {
        $ascii = (string) preg_replace('/[^\x20-\x7E]/', '_', strtr(new UnicodeString($name)->ascii()->toString(), '%', '_'));

        return 1 === preg_match('/[a-z0-9]/i', $ascii) ? $ascii : 'file';
    }
}
