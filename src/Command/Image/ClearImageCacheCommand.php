<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Command\Image;

use Gingerminds\MediaManagerBundle\Entity\File\FileInterface;
use Gingerminds\MediaManagerBundle\Image\ImageProcessor;
use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'gingerminds:media:cache:clear', description: 'Clear the rendered image presets')]
final class ClearImageCacheCommand extends Command
{
    public function __construct(
        private readonly FileRepository $files,
        private readonly ImageProcessor $images,
        private readonly string $defaultDisk,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only these file ids')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Skip the confirmation of a full purge');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var list<string> $ids */
        $ids = $input->getOption('file');

        return [] === $ids ? $this->clearAll($io, (bool) $input->getOption('force')) : $this->clearFiles($io, $ids);
    }

    /**
     * @param list<string> $ids
     */
    private function clearFiles(SymfonyStyle $io, array $ids): int
    {
        foreach ($ids as $id) {
            $file = $this->files->find($id);

            if (!$file instanceof FileInterface) {
                $io->warning(\sprintf('File "%s" not found, skipped.', $id));

                continue;
            }

            $this->images->clear($file);
            $io->writeln(\sprintf('Cleared the presets of file "%s".', $id));
        }

        return Command::SUCCESS;
    }

    private function clearAll(SymfonyStyle $io, bool $force): int
    {
        if (!$force && !$io->confirm('This deletes every rendered preset on every disk in use. Continue?', false)) {
            return Command::SUCCESS;
        }

        foreach ($this->files->findDisks() ?: [$this->defaultDisk] as $disk) {
            $this->images->clearAll($disk);
            $io->writeln(\sprintf('Cleared the presets on disk "%s".', $disk));
        }

        return Command::SUCCESS;
    }
}
