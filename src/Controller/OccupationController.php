<?php

namespace App\Controller;

use App\Form\OccupationType;
use App\DTO\ParkingOccupationLineDTO;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OccupationController extends AbstractController
{

    private array $counters;
    private array $configuration;

    public function __construct(private string $jsonFile, private TranslatorInterface $translator, private HttpClientInterface $client, private KernelInterface $kernel) 
    {
        $content = file_get_contents($jsonFile);
        $this->counters = json_decode($content, true);
    }

    #[Route('/{_locale}/occupation/{counter}/edit', name: 'occupation_reset')]
    public function edit(Request $request, $counter): Response
    {
        $occupation = $this->readActualOccupation();
        $form = $this->createForm(OccupationType::class, [
            'occupation' => $occupation,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->reset($counter, $data);
        }

        return $this->render('occupation/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{_locale}/occupation/{counter}', name: 'occupation_index')]
    public function index(Request $request, $counter)
    {
        $contentTypeJson = $request->getContentTypeFormat() === 'json' || $request->get('ajax')? true : false;
        if (!array_key_exists($counter, $this->counters)) {
            $this->addFlash('error', 'error.counterNotFound');
            return $this->render('occupation/error.html.twig');
        }
        $this->configuration = $this->counters[$counter];
        $response = $this->sendRequest($counter);
        if (404 === $response->getStatusCode()) {
            $this->addFlash(
                'error',
                $this->translator->trans('system.error', [
                    '%error%' => $this->configuration['endpointBase'] . ' not responding',
                ])
            );
        }
        if (401 === $response->getStatusCode() || 500 === $response->getStatusCode()) {
            $this->login($counter);
            $response = $this->sendRequest($counter);
        }
        if (200 === $response->getStatusCode() || 202 === $response->getStatusCode()) {
            $responseBody = json_decode($response->getContent(), true);
            $zones = $responseBody['centre']['zones'];
            if (!$contentTypeJson) {
                $template = $this->configuration['template']  ?? 'occupation/default.html.twig';
                return $this->render($template, [
                    'zones' => $zones,
                ]);
            }
            $count = count($zones);
            if ($count > 0) {
                foreach ($zones as $row) {
                    $pol = ParkingOccupationLineDTO::createParkingOcupationFromData($row);
                    $results[] = $pol;
                }
                return $this->json($results);
            }
        }
        return $this->render('occupation/error.html.twig');
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

    private function sendRequest(string $counter) {
        $version = $this->configuration['version'];
        $username = $this->configuration['username'];
        $centreId = $this->configuration['centre'];
        $tokenFile = $this->readToken($counter);
        $tokenArray = json_decode($tokenFile, true);
        $response = $this->client->request('GET', $this->configuration['endpointBase'] . "/v$version/secure/clients/$username/centre/$centreId/realtimezone", [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $tokenArray['token'],
            ],
        ]);
        return $response;
    }


    private function readToken(string $counter): string
    {
        $tokenFilePath = $this->kernel->getProjectDir().'/'.$this->configuration['tokenFile'];
        $tokenFile = @file_get_contents($tokenFilePath);
        /* if it doesn't exists */
        if (false === $tokenFile) {
            $this->login($counter);
            $tokenFile = file_get_contents($tokenFilePath);
        }
        return $tokenFile;
    }

    private function login(string $counter): Response
    {
        return $this->executeCommand('app:update-token', $counter);
    }

    private function reset(string $counter, int $occupation): Response
    {
        return $this->executeCommand('app:reset-occupation', $counter, [ 'occupation' => $occupation]);
    }

    private function executeCommand(string $command, string $counter, array $params = null): Response
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $paramsArray = ['command' => $command];
        $paramsArray['counter'] = $counter;
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
