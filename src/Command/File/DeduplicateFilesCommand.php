<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Command\File;

use Gingerminds\MediaManagerBundle\File\Maintenance\FileDeduplicator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'gingerminds:media:files:deduplicate', description: 'Merge the files with the same content into the oldest one')]
final class DeduplicateFilesCommand extends Command
{
    public function __construct(
        private readonly FileDeduplicator $deduplicator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the merges without doing them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $report = $this->deduplicator->deduplicate($dryRun);

        if ([] === $report) {
            $io->success('No duplicate (run gingerminds:media:files:hash first on an imported database).');

            return Command::SUCCESS;
        }

        $io->table(
            ['Kept', 'Duplicates', 'References updated'],
            array_map(static fn (array $group): array => [$group['keep'], implode("\n", $group['duplicates']), $group['updated'] ?? '-'], $report),
        );

        $count = array_sum(array_map(static fn (array $group): int => \count($group['duplicates']), $report));
        $dryRun
            ? $io->note(\sprintf('%d duplicate(s) to merge (dry run).', $count))
            : $io->success(\sprintf('%d duplicate(s) merged.', $count));

        return Command::SUCCESS;
    }
}
