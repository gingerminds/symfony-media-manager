<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Http;

use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers of every response serving a stored file: never sniffed, and a script (SVG, HTML...)
 * opened directly never runs in the application origin. An `<img>` is not affected.
 */
final class FileResponseHeaders
{
    public const string CONTENT_SECURITY_POLICY = "default-src 'none'; style-src 'unsafe-inline'; sandbox";

    /**
     * Shown by the browser PDF viewer, which refuses a sandboxed document; a PDF never runs scripts in the origin.
     */
    private const array WITHOUT_CSP = ['application/pdf'];

    public static function apply(Response $response, ?string $mimeType): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        if (!\in_array(strtolower(trim(explode(';', $mimeType ?? '')[0])), self::WITHOUT_CSP, true)) {
            $response->headers->set('Content-Security-Policy', self::CONTENT_SECURITY_POLICY);
        }

        return $response;
    }
}
