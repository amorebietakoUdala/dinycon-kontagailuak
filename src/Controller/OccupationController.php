<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

class OccupationController extends AbstractController
{
    /**
     * @Route("{_locale}/occupation", name="occupation")
     */
    public function index(KernelInterface $kernel)
    {
        $baseUrl = $this->getParameter('apiEndpoint');
        $client = HttpClient::create();
        $tokenFile = file_get_contents($this->getParameter('tokenFile'));
        $tokenArray = json_decode($tokenFile, true);
        $response = $client->request('GET', $this->getParameter('apiEndpoint').'/v1/secure/clients/gane/realtime', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$tokenArray['token'],
            ],
        ]);
        if (500 === $response->getStatusCode()) {
            $this->login($kernel);
            $tokenFile = file_get_contents($this->getParameter('tokenFile'));
            $tokenArray = json_decode($tokenFile, true);
            $response = $client->request('GET', $this->getParameter('apiEndpoint').'/v1/secure/clients/gane/realtime', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$tokenArray['token'],
                ],
            ]);
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
                'occupation' => $input - $output,
            ]);
        }

        return $this->json($tokenArray);
    }

    private function login(KernelInterface $kernel)
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'app:update-token',
        ]);

        // You can use NullOutput() if you don't need the output
        $output = new BufferedOutput();
        $application->run($input, $output);

        // return the output, don't use if you used NullOutput()
        $content = $output->fetch();

        // return new Response(""), if you used NullOutput()
        return new Response($content);
    }
}
