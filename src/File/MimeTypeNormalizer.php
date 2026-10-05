<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

use Symfony\Component\Mime\MimeTypesInterface;

/**
 * Office mime types become "application/<extension>"; an OOXML guess is checked against its zip content.
 */
class MimeTypeNormalizer
{
    private const string DEFAULT = 'application/octet-stream';

    private const string MARKER_WORD = 'word/document.xml';
    private const string MARKER_EXCEL = 'xl/workbook.xml';
    private const string MARKER_POWERPOINT = 'ppt/presentation.xml';

    /**
     * @var array<string, string>
     */
    private const array MAP = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'application/docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.template' => 'application/dotx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'application/xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.template' => 'application/xltx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'application/pptx',
        'application/vnd.openxmlformats-officedocument.presentationml.template' => 'application/potx',
        'application/vnd.openxmlformats-officedocument.presentationml.slideshow' => 'application/ppsx',
        'application/msword' => 'application/doc',
        'application/vnd.ms-excel' => 'application/xls',
        'application/vnd.ms-powerpoint' => 'application/ppt',
        'application/vnd.oasis.opendocument.text' => 'application/odt',
        'application/vnd.oasis.opendocument.spreadsheet' => 'application/ods',
        'application/vnd.oasis.opendocument.presentation' => 'application/odp',
    ];

    /**
     * Zip entry required by each OOXML type: libmagic sometimes mistakes a plain zip for one.
     *
     * @var array<string, string>
     */
    private const array OOXML_MARKERS = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => self::MARKER_WORD,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.template' => self::MARKER_WORD,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => self::MARKER_EXCEL,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.template' => self::MARKER_EXCEL,
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => self::MARKER_POWERPOINT,
        'application/vnd.openxmlformats-officedocument.presentationml.template' => self::MARKER_POWERPOINT,
        'application/vnd.openxmlformats-officedocument.presentationml.slideshow' => self::MARKER_POWERPOINT,
    ];

    public function __construct(
        private readonly MimeTypesInterface $mimeTypes,
    ) {
    }

    /**
     * Guessed from the content, then normalized.
     */
    public function guess(string $path): string
    {
        return $this->normalize($this->mimeTypes->guessMimeType($path) ?? self::DEFAULT, $path);
    }

    public function normalize(string $mimeType, ?string $path = null): string
    {
        if (null !== $path && isset(self::OOXML_MARKERS[$mimeType])) {
            $hasMarker = $this->zipHasEntry($path, self::OOXML_MARKERS[$mimeType]);

            if (false === $hasMarker) {
                return 'application/zip';
            }

            if (null === $hasMarker) {
                return $mimeType;
            }
        }

        return self::MAP[$mimeType] ?? $mimeType;
    }

    /**
     * Null when the file is not a zip.
     */
    private function zipHasEntry(string $path, string $entry): ?bool
    {
        $zip = new \ZipArchive();

        if (true !== $zip->open($path)) {
            return null;
        }

        $found = false !== $zip->locateName($entry);
        $zip->close();

        return $found;
    }
}
