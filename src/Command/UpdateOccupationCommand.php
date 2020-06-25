<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class UpdateOccupationCommand extends Command
{
    private $username;
    private $password;
    private $client;
    private $io;
    private $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->username = $params->get('apiUsername');
        $this->password = $params->get('apiPassword');
        $this->client = HttpClient::create();
        parent::__construct();
    }

    protected static $defaultName = 'app:update-occupation';

    protected function configure()
    {
        $this
            ->setDescription('This command updates the occupation data')
//            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
//            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        try {
            $tokenFile = @file_get_contents($this->params->get('tokenFile'));
            /* if it doesn't exists */
            if (false === $tokenFile) {
                $this->login();
                $tokenFile = file_get_contents($this->params->get('tokenFile'));
            }
            $client = HttpClient::create();
            $tokenArray = json_decode($tokenFile, true);
            $response = $client->request('GET', $this->params->get('apiEndpoint').'/v1/secure/clients/gane/centre/86/historic?last=5', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$tokenArray['token'],
                ],
            ]);
            if (500 === $response->getStatusCode()) {
                $this->login();
                $tokenFile = file_get_contents($this->params->get('tokenFile'));
                $tokenArray = json_decode($tokenFile, true);
                $response = $client->request('GET', $this->params->get('apiEndpoint').'/v1/secure/clients/gane/centre/86/historic?last=5', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer '.$tokenArray['token'],
                    ],
                ]);
            }
            $responseBody = json_decode($response->getContent(), true);
            $lastRecord = $responseBody['centre']['accesses'][0]['data'][0];
            $occupationFile = file_get_contents($this->params->get('occupationFile'));
            $actualOccupation = json_decode($occupationFile, true);
            $lastRecordDateTime = new \DateTime($lastRecord['date'].' '.$lastRecord['time']);
            if (array_key_exists('date', $actualOccupation) && $actualOccupation['time']) {
                $actualOccupationDateTime = new \DateTime($actualOccupation['date'].' '.$actualOccupation['time']);
            } else {
                $actualOccupationDateTime = new \DateTime(date('Y-m-d'));
            }
            if ($lastRecordDateTime > $actualOccupationDateTime) {
                $actualOccupation['occupation'] = $actualOccupation['occupation'] + $lastRecord['input'] - $lastRecord['output'];
                $merge = array_merge($actualOccupation, $lastRecord);
                $filesystem = new \Symfony\Component\Filesystem\Filesystem();
                $filesystem->dumpFile($this->params->get('occupationFile'), json_encode($merge));
            }
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
        }

        return 0;
    }

    private function login(): ?string
    {
        $response = $this->client->request('POST', $this->params->get('apiEndpoint').'/v1/auth/login', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => '{"username": "'.$this->username.'","password": "'.$this->password.'"}',
        ]);
        $statusCode = $response->getStatusCode();
        if (200 === $statusCode) {
            $responseArray = $response->toArray();
            if (array_key_exists('token', $responseArray)) {
                $filesystem = new \Symfony\Component\Filesystem\Filesystem();
                $filesystem->dumpFile($this->params->get('tokenFile'), json_encode($responseArray));
            }

            return null;
        }
        throw new \Exception("Can't login to the API endpoint");
    }
}
