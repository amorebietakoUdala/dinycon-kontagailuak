<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class ResetOccupationCommand extends Command
{
    protected static $defaultName = 'app:reset-occupation';
    private $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Resets the occupation file to 0')
            ->addArgument('occupation', InputArgument::OPTIONAL, 'Integer to reset to');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $resetNumber = 0;
        if (null !== $input->getArgument('occupation')) {
            $resetNumber = $input->getArgument('occupation');
        }
        $filesystem = new Filesystem();
        $filesystem->dumpFile(
            $this->params->get('occupationFile'),
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
