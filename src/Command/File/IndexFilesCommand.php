<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Command\File;

use Gingerminds\MediaManagerBundle\Exception\TranslatableExceptionInterface;
use Gingerminds\MediaManagerBundle\File\Maintenance\FileIndexer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'gingerminds:media:files:index', description: 'Create the missing rows of the files on the library disk')]
final class IndexFilesCommand extends Command
{
    public function __construct(
        private readonly FileIndexer $indexer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Only this directory (relative to the library root) and its subdirectories', '')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the files without indexing them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $missing = $this->indexer->missing(trim((string) $input->getOption('path'), '/'));
        } catch (TranslatableExceptionInterface $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ([] === $missing) {
            $io->success('Every file is indexed.');

            return Command::SUCCESS;
        }

        if ($input->getOption('dry-run')) {
            $io->listing($missing);
            $io->note(\sprintf('%d file(s) to index (dry run).', \count($missing)));

            return Command::SUCCESS;
        }

        $io->progressStart(\count($missing));
        $this->indexer->index($missing, static fn () => $io->progressAdvance());
        $io->progressFinish();
        $io->success(\sprintf('%d file(s) indexed.', \count($missing)));

        return Command::SUCCESS;
    }
}
