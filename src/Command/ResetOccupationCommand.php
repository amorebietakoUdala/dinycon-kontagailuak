<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'app:reset-occupation')]
class ResetOccupationCommand extends Command
{
    private array $counters;
    private array $configuration;

    public function __construct(private string $projectDir, private string $jsonFile)
    {
        $content = file_get_contents($jsonFile);
        $this->counters = json_decode($content, true);
        parent::__construct();
    }


    protected function configure(): void
    {
        $this
            ->setDescription('Resets the occupation file to 0')
            ->addArgument('counter', InputArgument::REQUIRED, 'Counter to reset')
            ->addArgument('occupation', InputArgument::OPTIONAL, 'Integer to reset to');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $counter = $input->getArgument('counter');
        $this->configuration = $this->counters[$counter];
        $io = new SymfonyStyle($input, $output);
        $resetNumber = 0;
        if (null !== $input->getArgument('occupation')) {
            $resetNumber = $input->getArgument('occupation');
        }
        $filesystem = new Filesystem();
        $filesystem->dumpFile(
            $this->projectDir.'/'.$this->configuration['occupationFile'],
            json_encode([
                'occupation' => $resetNumber,
                'inputs' => 0,
                'outputs' => 0,
                'timestamp' => (new \DateTime())->format('Y-m-d h:i:s'),
            ])
        );
        $io->success('Occupation reseted to ' . $resetNumber);

        return 0;
    }
}
