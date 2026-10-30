<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\File;

use Symfony\Component\Mime\MimeTypesInterface;

/**
 * The mime type of a file, guessed from its content; an OOXML guess is checked against its zip
 * content (libmagic sometimes mistakes a plain zip for one).
 */
class MimeTypeGuesser
{
    private const string DEFAULT = 'application/octet-stream';

    private const string MARKER_WORD = 'word/document.xml';
    private const string MARKER_EXCEL = 'xl/workbook.xml';
    private const string MARKER_POWERPOINT = 'ppt/presentation.xml';

    /**
     * Zip entry required by each OOXML type.
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

    public function guess(string $path): string
    {
        $mimeType = $this->mimeTypes->guessMimeType($path) ?? self::DEFAULT;

        if (isset(self::OOXML_MARKERS[$mimeType]) && false === $this->zipHasEntry($path, self::OOXML_MARKERS[$mimeType])) {
            return 'application/zip';
        }

        return $mimeType;
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
