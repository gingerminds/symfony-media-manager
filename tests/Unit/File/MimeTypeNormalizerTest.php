<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Unit\File;

use Gingerminds\MediaManagerBundle\File\MimeTypeNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\MimeTypes;

final class MimeTypeNormalizerTest extends TestCase
{
    private const string XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private MimeTypeNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new MimeTypeNormalizer(new MimeTypes());
    }

    public function testOfficeTypesAreShortened(): void
    {
        self::assertSame('application/xlsx', $this->normalizer->normalize(self::XLSX));
        self::assertSame('application/doc', $this->normalizer->normalize('application/msword'));
        self::assertSame('application/odt', $this->normalizer->normalize('application/vnd.oasis.opendocument.text'));
    }

    public function testOtherTypesAreUnchanged(): void
    {
        self::assertSame('image/png', $this->normalizer->normalize('image/png'));
        self::assertSame('application/pdf', $this->normalizer->normalize('application/pdf'));
    }

    public function testAnOoxmlGuessIsCheckedAgainstTheZipContent(): void
    {
        $xlsx = $this->zip('xl/workbook.xml');
        $plainZip = $this->zip('readme.txt');
        $notAZip = $this->temporaryFile('plain text');

        self::assertSame('application/xlsx', $this->normalizer->normalize(self::XLSX, $xlsx));
        self::assertSame('application/zip', $this->normalizer->normalize(self::XLSX, $plainZip));
        self::assertSame(self::XLSX, $this->normalizer->normalize(self::XLSX, $notAZip));
    }

    public function testGuessReadsTheContent(): void
    {
        self::assertSame('application/docx', $this->normalizer->guess($this->zip('word/document.xml', '[Content_Types].xml')));
        self::assertSame('text/plain', $this->normalizer->guess($this->temporaryFile('plain text')));
    }

    private function zip(string ...$entries): string
    {
        $path = $this->temporaryFile('');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);

        foreach ($entries as $entry) {
            $zip->addFromString($entry, '<xml/>');
        }

        $zip->close();

        return $path;
    }

    private function temporaryFile(string $content): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'gm-mime');
        file_put_contents($path, $content);

        return $path;
    }
}
