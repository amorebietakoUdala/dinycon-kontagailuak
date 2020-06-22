<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class UpdateTokenCommand extends Command
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

    protected static $defaultName = 'app:update-token';

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
            $content = $this->login();
            if (null !== $content) {
                $filesystem = new Filesystem();
                $filesystem->dumpFile($this->params->get('tokenFile'), $content);
                $this->io->success('Data updated!!');
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
                return $response->getContent();
            }
        }
        throw  new \Exception($response->getContent());
        return null;
    }
}
