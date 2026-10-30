<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Command\File;

use Gingerminds\MediaManagerBundle\Repository\File\FileRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'gingerminds:media:files:mime-types', description: 'Replace the Laravel mime types of the Office documents ("application/docx") by the real ones')]
final class MimeTypesCommand extends Command
{
    /**
     * The types the Laravel media manager stored, by real type.
     */
    public const array LEGACY = [
        'application/docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
        'application/xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
        'application/pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
        'application/ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
        'application/doc' => 'application/msword',
        'application/xls' => 'application/vnd.ms-excel',
        'application/ppt' => 'application/vnd.ms-powerpoint',
        'application/odt' => 'application/vnd.oasis.opendocument.text',
        'application/ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'application/odp' => 'application/vnd.oasis.opendocument.presentation',
    ];

    public function __construct(
        private readonly FileRepository $files,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count the files without changing them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $rows = [];
        $total = 0;

        foreach (self::LEGACY as $legacy => $mimeType) {
            $count = $dryRun ? $this->files->count(['mimeType' => $legacy]) : $this->files->replaceMimeType($legacy, $mimeType);

            if ($count > 0) {
                $rows[] = [$legacy, $mimeType, $count];
                $total += $count;
            }
        }

        if ([] !== $rows) {
            $io->table(['Laravel', 'Mime type', 'Files'], $rows);
        }

        $dryRun ? $io->note(\sprintf('%d file(s) to update (dry run).', $total)) : $io->success(\sprintf('%d file(s) updated.', $total));

        return Command::SUCCESS;
    }
}
