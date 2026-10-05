<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Unit\File;

use Gingerminds\MediaManagerBundle\Exception\InvalidPathException;
use Gingerminds\MediaManagerBundle\File\PathGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class PathGuardTest extends TestCase
{
    public function testNormalize(): void
    {
        $guard = $this->guard();

        self::assertSame('a/b', $guard->normalize('a//b/'));
        self::assertSame('', $guard->normalize(''));
        self::assertSame('dossier été/2026', $guard->normalize('dossier été/2026'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPaths(): iterable
    {
        yield 'absolute' => ['/etc/passwd'];
        yield 'parent' => ['a/../../b'];
        yield 'current' => ['a/./b'];
        yield 'backslash' => ['a\\..\\b'];
        yield 'null byte' => ["a\0b"];
        yield 'new line' => ["a\nb"];
    }

    #[DataProvider('invalidPaths')]
    public function testInvalidPathIsRejected(string $path): void
    {
        $this->expectException(InvalidPathException::class);

        $this->guard()->normalize($path);
    }

    public function testInLibrary(): void
    {
        $guard = $this->guard('library/');

        self::assertSame('library', $guard->inLibrary(''));
        self::assertSame('library/fr/news', $guard->inLibrary('/fr/news'));
    }

    public function testAnInvalidRootIsRejectedAtRuntime(): void
    {
        $this->expectException(InvalidPathException::class);

        $this->guard('../outside')->inLibrary('');
    }

    public function testFileName(): void
    {
        $guard = $this->guard();

        self::assertSame('mon-fichier-ete.pdf', $guard->fileName('Mon Fichier été.PDF'));
        self::assertSame('archive-tar.gz', $guard->fileName('Archive.tar.gz'));
        self::assertSame('file.png', $guard->fileName('***.png'));
        self::assertSame('readme', $guard->fileName('README'));
    }

    private function guard(string $root = 'library'): PathGuard
    {
        return new PathGuard($root, new AsciiSlugger());
    }
}
