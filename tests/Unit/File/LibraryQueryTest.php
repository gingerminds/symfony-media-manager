<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Unit\File;

use Gingerminds\MediaManagerBundle\File\LibraryQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LibraryQueryTest extends TestCase
{
    /**
     * @param list<string> $accept
     * @param list<string> $types
     */
    #[DataProvider('accepts')]
    public function testTheTypesOfAnAcceptList(array $accept, array $types): void
    {
        self::assertSame($types, LibraryQuery::typesFor($accept));
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function accepts(): iterable
    {
        yield 'everything' => [[], ['image', 'video', 'audio', 'pdf', 'document', 'archive']];
        yield 'a family' => [['image/*'], ['image']];
        yield 'an exact type' => [['application/pdf'], ['pdf']];
        yield 'several' => [['image/png', 'application/xlsx', 'application/zip'], ['image', 'document', 'archive']];
        yield 'a family holding several types' => [['application/*'], ['pdf', 'document', 'archive']];
        yield 'a prefix of a type' => [['application/x-rar-compressed'], ['archive']];
        yield 'no type' => [['application/json'], []];
    }
}
