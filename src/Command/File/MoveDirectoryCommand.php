<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Command\File;

use Gingerminds\MediaManagerBundle\Exception\TranslatableExceptionInterface;
use Gingerminds\MediaManagerBundle\File\FileLibrary;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * No limit of files, unlike the admin (library.max_directory_move).
 */
#[AsCommand(name: 'gingerminds:media:directory:move', description: 'Move and/or rename a directory of the file library')]
final class MoveDirectoryCommand extends Command
{
    public function __construct(
        private readonly FileLibrary $library,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Directory to move, relative to the library root')
            ->addArgument('parent', InputArgument::OPTIONAL, 'New parent directory ("" for the root; default: the current parent)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'New name of the directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = trim((string) $input->getArgument('path'), '/');
        $parent = $this->parent($input->getArgument('parent'), $path);
        $name = $input->getOption('name');

        try {
            $newPath = $this->library->moveDirectory($path, $parent, \is_string($name) && '' !== $name ? $name : null);
        } catch (TranslatableExceptionInterface $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('"%s" is now "%s".', $path, $newPath));

        return Command::SUCCESS;
    }

    /**
     * The given parent, or the current one of the directory.
     */
    private function parent(mixed $argument, string $path): string
    {
        if (\is_string($argument)) {
            return trim($argument, '/');
        }

        return str_contains($path, '/') ? \dirname($path) : '';
    }
}
