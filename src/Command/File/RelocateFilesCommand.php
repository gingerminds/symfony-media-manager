<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Command\File;

use Gingerminds\MediaManagerBundle\File\Maintenance\FileRelocator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'gingerminds:media:files:relocate', description: 'Move the files outside the library root into it')]
final class RelocateFilesCommand extends Command
{
    public function __construct(
        private readonly FileRelocator $relocator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the files without moving them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $groups = $this->relocator->outside();

        if ([] === $groups) {
            $io->success('Every file is in the library.');

            return Command::SUCCESS;
        }

        if ($input->getOption('dry-run')) {
            $io->listing(array_keys($groups));
            $io->note(\sprintf('%d file(s) to move to the library root (dry run).', \count($groups)));

            return Command::SUCCESS;
        }

        $io->progressStart(\count($groups));
        $result = $this->relocator->relocate($groups, static fn () => $io->progressAdvance());
        $io->progressFinish();

        $io->table(['From', 'To'], array_map(null, array_keys($result['moved']), array_values($result['moved'])));

        if ([] !== $result['missing']) {
            $io->warning(\sprintf('%d file(s) missing on their disk, not moved:', \count($result['missing'])));
            $io->listing($result['missing']);
        }

        $io->success(\sprintf('%d file(s) moved.', \count($result['moved'])));

        return Command::SUCCESS;
    }
}
