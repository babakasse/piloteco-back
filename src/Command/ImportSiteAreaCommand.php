<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Import\SiteAreaImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'piloteco:import:surfaces',
    description: 'Import site surface areas from the Surface_of_Sites CSV export',
)]
final class ImportSiteAreaCommand extends Command
{
    public function __construct(
        private readonly SiteAreaImportService $importService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to the surfaces CSV file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');

        if (!file_exists($filePath)) {
            $io->error(sprintf('File not found: %s', $filePath));
            return Command::FAILURE;
        }

        $io->title('Piloteco — Site Area Import');
        $io->text(sprintf('Processing file: %s', $filePath));

        $startTime = microtime(true);
        $stats = $this->importService->import($filePath);
        $duration = round(microtime(true) - $startTime, 2);

        $io->success(sprintf('Import completed in %ss', $duration));
        $io->table(
            ['Created', 'Updated', 'Skipped', 'Total processed'],
            [[$stats->getCreated(), $stats->getUpdated(), $stats->getSkipped(), $stats->getTotal()]],
        );

        return Command::SUCCESS;
    }
}
