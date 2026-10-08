<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Command\File;

use Gingerminds\CoreBundle\Model\TimestampableInterface;
use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\File\Maintenance\OrphanFinder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'gingerminds:media:files:orphans', description: 'List, and delete with --delete, the files used nowhere')]
final class OrphanFilesCommand extends Command
{
    public function __construct(
        private readonly OrphanFinder $orphans,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('delete', null, InputOption::VALUE_NONE, 'Delete them')
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Only the files created more than this number of days ago');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $olderThan = $input->getOption('older-than');

        if (null !== $olderThan && !ctype_digit((string) $olderThan)) {
            $io->error('--older-than is a number of days.');

            return Command::INVALID;
        }

        $files = $this->orphans->find(null === $olderThan ? null : (int) $olderThan);

        if ([] === $files) {
            $io->success('No unused file.');

            return Command::SUCCESS;
        }

        $io->table(['File', 'Size', 'Created'], array_map(static fn (FileInterface $file): array => [
            $file->getDisk() . ':' . $file->getPath(),
            $file->getSize(),
            $file instanceof TimestampableInterface ? $file->getCreatedAt()?->format('Y-m-d') : null,
        ], $files));

        if (!$input->getOption('delete')) {
            $io->note(\sprintf('%d unused file(s), --delete deletes them.', \count($files)));

            return Command::SUCCESS;
        }

        $result = $this->orphans->delete($files);
        $io->success(\sprintf('%d file(s) deleted, %d kept (used in the meantime).', \count($result->deleted), \count($result->blocked)));

        return Command::SUCCESS;
    }
}
