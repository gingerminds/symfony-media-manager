<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Command\File;

use Gingerminds\MediaManagerBundle\File\Maintenance\FileHasher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'gingerminds:media:files:hash', description: 'Compute the sha256 hash of the files without one')]
final class HashFilesCommand extends Command
{
    public function __construct(
        private readonly FileHasher $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Hash every file again');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $count = $this->hasher->count($force);

        $io->progressStart($count);
        $missing = $this->hasher->hash($force, static fn () => $io->progressAdvance());
        $io->progressFinish();

        if ([] !== $missing) {
            $io->warning(\sprintf('%d file(s) missing on their disk, not hashed:', \count($missing)));
            $io->listing($missing);
        }

        $io->success(\sprintf('%d file(s) hashed.', $count - \count($missing)));

        return Command::SUCCESS;
    }
}
