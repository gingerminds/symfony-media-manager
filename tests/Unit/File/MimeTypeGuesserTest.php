<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Unit\File;

use Gingerminds\MediaManagerBundle\File\MimeTypeGuesser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\MimeTypes;

final class MimeTypeGuesserTest extends TestCase
{
    private const string DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    private MimeTypeGuesser $guesser;

    protected function setUp(): void
    {
        $this->guesser = new MimeTypeGuesser(new MimeTypes());
    }

    public function testAnOoxmlGuessIsCheckedAgainstTheZipContent(): void
    {
        self::assertSame(self::DOCX, $this->guesser->guess($this->zip('word/document.xml', '[Content_Types].xml')));
        self::assertSame('application/zip', $this->guesser->guess($this->zip('[Content_Types].xml', 'readme.txt')));
    }

    public function testOtherTypesAreTheGuessedOnes(): void
    {
        self::assertSame('text/plain', $this->guesser->guess($this->temporaryFile('plain text')));
        self::assertSame('application/zip', $this->guesser->guess($this->zip('readme.txt')));
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
