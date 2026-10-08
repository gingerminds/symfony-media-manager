<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\Command;

use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Gingerminds\MediaManagerBundle\Tests\Functional\FileFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class MoveDirectoryCommandTest extends KernelTestCase
{
    public function testMoveAndRenameWithoutTheAdminLimit(): void
    {
        self::bootKernel();
        /** @var FileLibrary $library */
        $library = self::getContainer()->get('test.file_library');
        $files = new FileFactory(self::getContainer()->get('test.file_storage'));
        $library->mkdir('', 'archives');
        $library->mkdir('archives', 'docs');

        foreach (['a', 'b', 'c', 'd'] as $name) {
            $files->text($name . '.txt', 'content ' . $name, 'archives/docs');
        }

        $tester = new CommandTester(new Application(self::$kernel)->find('gingerminds:media:directory:move'));

        $tester->execute(['path' => 'archives/docs', '--name' => 'Docs 2026']);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('"archives/docs" is now "archives/docs-2026"', $tester->getDisplay());

        $tester->execute(['path' => 'archives/docs-2026', 'parent' => '']);
        $tester->assertCommandIsSuccessful();
        self::assertSame(['archives', 'docs-2026'], $library->directories());

        $tester->execute(['path' => 'archives', 'parent' => 'missing']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('does not exist', $tester->getDisplay());
    }
}
