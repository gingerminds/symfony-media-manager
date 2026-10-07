<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Unit\File;

use Gingerminds\MediaManagerBundle\File\MimeTypePatterns;
use PHPUnit\Framework\TestCase;

final class MimeTypePatternsTest extends TestCase
{
    public function testPatterns(): void
    {
        self::assertSame(['image/*', 'application/pdf'], MimeTypePatterns::normalize([' Image/* ', 'application/pdf', 'image/*', '*', 'image', "image/png'", 3]));

        self::assertTrue(MimeTypePatterns::matches('image/png', ['image/*']));
        self::assertTrue(MimeTypePatterns::matches('application/pdf', ['image/*', 'application/pdf']));
        self::assertFalse(MimeTypePatterns::matches('application/pdf', ['image/*']));
        self::assertFalse(MimeTypePatterns::matches('imagex/png', ['image/*']));
        self::assertTrue(MimeTypePatterns::matches('text/plain', []));

        self::assertSame('image/%', MimeTypePatterns::toLike('image/*'));
        self::assertSame('application/pdf', MimeTypePatterns::toLike('application/pdf'));
    }
}
