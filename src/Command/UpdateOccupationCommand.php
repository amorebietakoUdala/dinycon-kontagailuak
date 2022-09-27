<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Filesystem\Filesystem;

class UpdateOccupationCommand extends Command
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

    protected static $defaultName = 'app:update-occupation';

    protected function configure()
    {
        $this
            ->setDescription('This command updates the occupation data')
            ->addArgument('counter', InputArgument::REQUIRED, 'Name of the counter to update-occupation')
            //            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $counter = $input->getArgument('counter');
        $this->io = new SymfonyStyle($input, $output);
        try {
            $this->configuration = $this->counters[$counter];
            $tokenFile = $this->readTokenFile($counter);
            $tokenArray = json_decode($tokenFile, true);
            $now = new \DateTime(); //current date/time
            $endDate = $now->format('Y-m-dH:i');
            $now->add(\DateInterval::createFromDateString('-1 hour'));
            $startDate = $now->format('Y-m-dH:i');
            $username = $this->configuration['username'];
            $idCentre = $this->configuration['centre'];
            $response = $this->client->request('GET', $this->configuration['endpointBase']."/v".$this->configuration['version']."/secure/clients/$username/centre/$idCentre/historic?start=$startDate&end=$endDate", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => $tokenArray['token'],
                ],
            ]);
            if (401 === $response->getStatusCode() || 500 === $response->getStatusCode()) {
                $this->login($counter);
                $tokenFile = file_get_contents($this->projectDir.'/'.$this->configuration['occupationFile']);
                $tokenArray = json_decode($tokenFile, true);
                $response = $this->client->request('GET', $this->configuration['endpointBase']."/v".$this->configuration['version']."/secure/clients/$username/centre/$idCentre/historic?start=$startDate&end=$endDate", [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => $tokenArray['token'],
                    ],
                ]);
            }
            $responseBody = json_decode($response->getContent(), true);
            $lastRecord = $responseBody['centre']['accesses'][0]['data'][0];
            $occupationFile = $this->getOrCreateOccupationFile($this->configuration['occupationFile']);
            $actualOccupation = json_decode($occupationFile, true);
            $lastRecordDateTime = new \DateTime($lastRecord['timestamp']);
            if (null !== $actualOccupation && array_key_exists('timestamp', $actualOccupation)) {
                $actualOccupationDateTime = new \DateTime($actualOccupation['timestamp']);
            } else {
                $actualOccupationDateTime = new \DateTime(date('Y-m-d'));
            }
            if ($lastRecordDateTime > $actualOccupationDateTime) {
                $actualOccupation['occupation'] = $actualOccupation['occupation'] + $lastRecord['inputs'] - $lastRecord['outputs'];
                $merge = array_merge($actualOccupation, $lastRecord);
                $filesystem = new Filesystem();
                $filesystem->dumpFile($this->configuration['occupationFile'], json_encode($merge));
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
                $filesystem = new Filesystem();
                $filesystem->dumpFile($this->configuration['tokenFile'], json_encode($responseArray));
            }
            return null;
        }
        throw new \Exception("Can't login to the API endpoint");
    }

    private function readTokenFile($counter): string
    {
        $tokenFile = $this->projectDir.'/'.$this->configuration['tokenFile'];
        $content = file_get_contents($tokenFile);
        /* if it doesn't exists */
        if (false === $content) {
            $content = $this->login($counter);
            $content = file_get_contents($tokenFile);
        }

        return $content;
    }

    private function getOrCreateOccupationFile($relativePath)
    {
        $occupationFile = null;
        $path = $this->projectDir.'/'.$relativePath;
        try {
            $occupationFile = file_get_contents($path);
        } catch (\Exception $e) {
        }
        if (null === $occupationFile) {
            $filesystem = new Filesystem();
            $actualOccupation = [
                'occupation' => 0,
                'inputs' => 0,
                'outputs' => 0
            ];
            $filesystem->dumpFile($path, json_encode($actualOccupation));
            $occupationFile = file_get_contents($path);
        }
        return $occupationFile;
    }
}
