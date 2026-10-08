<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\Command;

use Gingerminds\MediaManagerBundle\Basket\Repository\BasketRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'gingerminds:media:basket:purge', description: 'Delete the expired guest baskets (to run from a cron)')]
final class PurgeBasketsCommand extends Command
{
    public function __construct(
        private readonly BasketRepository $baskets,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        new SymfonyStyle($input, $output)->success(\sprintf('%d expired basket(s) deleted.', $this->baskets->deleteExpired()));

        return Command::SUCCESS;
    }
}
