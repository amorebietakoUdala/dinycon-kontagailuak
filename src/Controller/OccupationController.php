<?php

namespace App\Controller;

use App\Form\OccupationType;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

class OccupationController extends AbstractController
{
    /**
     * @Route("{_locale}/occupation/edit", name="occupation_reset")
     */
    public function edit(Request $request, KernelInterface $kernel): Response
    {
        $occupation = $this->readActualOccupation();
        $form = $this->createForm(OccupationType::class, [
            'occupation' => $occupation,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->reset($kernel, $data);
        }

        return $this->render('occupation/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("{_locale}/occupation", name="occupation_index")
     */
    public function index(KernelInterface $kernel)
    {
        $client = HttpClient::create();
        $tokenFile = $this->readToken($kernel);
        $tokenArray = json_decode($tokenFile, true);
        $response = $client->request('GET', $this->getParameter('apiEndpoint').'/v1/secure/clients/gane/realtime', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$tokenArray['token'],
            ],
        ]);
        if (500 === $response->getStatusCode()) {
            $this->login($kernel);
            $tokenFile = $this->readToken($kernel);
            $tokenArray = json_decode($tokenFile, true);
            $response = $client->request('GET', $this->getParameter('apiEndpoint').'/v1/secure/clients/gane/realtime', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$tokenArray['token'],
                ],
            ]);
        }
        if (200 === $response->getStatusCode()) {
            $responseBody = json_decode($response->getContent(), true);
            $input = $responseBody['centres'][0]['accesses'][0]['data'][0]['input'];
            $output = $responseBody['centres'][0]['accesses'][0]['data'][0]['output'];
            $occupation = (($input - $output) < 0) ? 0 : ($input - $output);

            return $this->render('occupation/index.html.twig', [
                'maximumCapacity' => $this->getParameter('maximumCapacity'),
                'input' => $input,
                'output' => $output,
                'actualOccupation' => $occupation,
            ]);
        }
    }

    /**
     * @Route("{_locale}/occupation/old", name="occupation_old")
     */
    public function old(KernelInterface $kernel)
    {
        $client = HttpClient::create();
        /* @ ommits the warning if the file doesn't exists */
        $tokenFile = $this->readToken($kernel);
        $occupation = $this->readActualOccupation();
        /* if it doesn't exists */
        $tokenArray = json_decode($tokenFile, true);
        $response = $client->request('GET', $this->getParameter('apiEndpoint').'/v1/secure/clients/gane/realtime', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$tokenArray['token'],
            ],
        ]);
        if (500 === $response->getStatusCode()) {
            dump('Before login');
            $tokenFile = $this->login($kernel);
            dump('After login');
            $tokenArray = json_decode($tokenFile, true);
            $response = $client->request('GET', $this->getParameter('apiEndpoint').'/v1/secure/clients/gane/realtime', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$tokenArray['token'],
                ],
            ]);
            dump($tokenFile, $tokenArray);
            $responseBody = json_decode($response->getContent(), true);
        }
        if (200 === $response->getStatusCode()) {
            $responseBody = json_decode($response->getContent(), true);
            $input = $responseBody['centres'][0]['accesses'][0]['data'][0]['input'];
            $output = $responseBody['centres'][0]['accesses'][0]['data'][0]['output'];

            return $this->render('occupation/index.html.twig', [
                'maximumCapacity' => $this->getParameter('maximumCapacity'),
                'input' => $input,
                'output' => $output,
                'sinceLastCall' => $input - $output,
                'actualOccupation' => $occupation + $input - $output,
            ]);
        }

        return $this->json($tokenArray);
    }

    /**
     * @Route("{_locale}/occupation/historic", name="occupation")
     */
    public function historic(KernelInterface $kernel)
    {
        $client = HttpClient::create();
        $tokenFile = $this->readToken($kernel);
        $tokenArray = json_decode($tokenFile, true);
        $response = $client->request('GET', $this->getParameter('apiEndpoint').'/v1/secure/clients/gane/centre/86/historic?start=2020-06-2400:00&end=2020-06-2414:00', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$tokenArray['token'],
            ],
        ]);
        dd($response->getContent());
    }

    private function readActualOccupation(): int
    {
        $occupationFile = @file_get_contents($this->getParameter('occupationFile'));
        if (false === $occupationFile) {
            $occupation = 0;
        } else {
            $occupationArray = json_decode($occupationFile, true);
            $occupation = $occupationArray['occupation'];
        }

        return $occupation;
    }

    private function updateOccupationFile($occupation)
    {
        $occupationFile = $this->getParameter('occupationFile');
        $filesystem = new Filesystem();
        $filesystem->dumpFile($occupationFile, json_encode([
            'occupation' => $occupation,
            ])
        );
    }

    private function readToken(KernelInterface $kernel): string
    {
        $tokenFile = @file_get_contents($this->getParameter('tokenFile'));
        /* if it doesn't exists */
        if (false === $tokenFile) {
            $this->login($kernel);
            $tokenFile = file_get_contents($this->getParameter('tokenFile'));
        }

        return $tokenFile;
    }

    private function login(KernelInterface $kernel): Response
    {
        return $this->executeCommand('app:update-token', $kernel);
    }

    private function reset(KernelInterface $kernel, $occupation): Response
    {
        return $this->executeCommand('app:reset-occupation', $kernel, $occupation);
    }

    private function executeCommand(string $command, KernelInterface $kernel, $params = null): Response
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $paramsArray = ['command' => $command];
        if (null !== $params) {
            $paramsArray = array_merge($paramsArray, $params);
        }
        $input = new ArrayInput($paramsArray);
        $output = new BufferedOutput();
        $application->run($input, $output);
        $content = $output->fetch();

        return new Response($content);
    }
}
