<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Functional\Command;

use Gingerminds\MediaManagerBundle\File\LibraryDirectories;
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
        /** @var LibraryDirectories $directories */
        $directories = self::getContainer()->get('test.library_directories');
        $files = new FileFactory(self::getContainer()->get('test.file_storage'));
        $directories->mkdir('', 'archives');
        $directories->mkdir('archives', 'docs');

        foreach (['a', 'b', 'c', 'd'] as $name) {
            $files->text($name . '.txt', 'content ' . $name, 'archives/docs');
        }

        $tester = new CommandTester(new Application(self::$kernel)->find('gingerminds:media:directory:move'));

        $tester->execute(['path' => 'archives/docs', '--name' => 'Docs 2026']);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('"archives/docs" is now "archives/docs-2026"', $tester->getDisplay());

        $tester->execute(['path' => 'archives/docs-2026', 'parent' => '']);
        $tester->assertCommandIsSuccessful();
        self::assertSame(['archives', 'docs-2026'], $directories->directories());

        $tester->execute(['path' => 'archives', 'parent' => 'missing']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('does not exist', $tester->getDisplay());
    }
}
