<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UpdateTokenCommand extends Command
{
    private array $counters;
    private array $configuration;
    private $io;

    public function __construct(private string $projectDir, private string $jsonFile, private HttpClientInterface $client)
    {
        $content = file_get_contents($jsonFile);
        $this->counters = json_decode($content, true);
        parent::__construct();
    }

    protected static $defaultName = 'app:update-token';

    protected function configure()
    {
        $this
            ->setDescription('This command updates the token file')
            ->addArgument('counter', InputArgument::REQUIRED, 'Name of the counter to update-token')
            //            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $counter = $input->getArgument('counter');
        $this->io = new SymfonyStyle($input, $output);
        try {
            $content = $this->login($counter);
            if (null !== $content) {
                $filesystem = new Filesystem();
                $tokenFile = $this->projectDir.'/'.$this->configuration['tokenFile'];
                $filesystem->dumpFile($tokenFile, $content);
                $this->io->success('Token updated!!');
            }
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function login($counter): ?string
    {
        $this->configuration = $this->counters[$counter];
        $response = $this->client->request('POST', $this->configuration['endpointBase']."/v".$this->configuration['version']."/auth/login", [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => '{"username": "' . $this->configuration['username'] . '","password": "' . $this->configuration['password'] . '"}',
        ]);
        $statusCode = $response->getStatusCode();
        if (200 === $statusCode || 202 === $statusCode) {
            $responseArray = $response->toArray();
            if (array_key_exists('token', $responseArray)) {
                return $response->getContent();
            }
        }
        throw  new \Exception($response->getContent());
        return null;
    }

}
