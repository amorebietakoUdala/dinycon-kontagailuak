<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class ParkingsController extends AbstractController
{

    public function __construct(private HttpClientInterface $client, private string $googleMapsKey) {}

    #[Route('/{_locale}/parkings', name: 'app_parkings')]
    public function index(): Response
    {
        return $this->redirectToRoute('occupation_index',[
            'counter' => 'parkings'
        ]);
    }

    #[Route('/{_locale}/parkings/map', name: 'app_parkings_map')]
    public function map(): Response
    {
        $response = $this->forward(OccupationController::class.'::index',[
            'counter' => 'parkings',
        ],[
            'ajax' => true,
        ]);

        $parkings = json_decode($response->getContent(),true);

        return $this->render("parkings/map.html.twig", [
            'parkings' => $parkings,
            'googleMapsKey' => $this->googleMapsKey,
        ]);
    }

    #[Route('/{_locale}/parkings/map/source.kml', name: 'app_parkings_map_source')]
    public function source(): Response
    {
        $response = $this->forward(OccupationController::class.'::index',[
            'counter' => 'parkings',
        ],[
            'ajax' => true,
        ]);

        $parkings = json_decode($response->getContent(),true);
        foreach ($parkings as $parking ) {
            $parkingsWithCoordinates[] = array_merge($this->getCoordinates($parking['nombre']), $parking); 
        }
        $response = new Response();
        $response->headers->set('Content-Type', 'application/vnd.google-earth.kml+xml');

        return  $this->render("parkings/parkingsLayer.kml.twig", [
            'parkings' => $parkingsWithCoordinates,
        ],$response);
    }

    private function getCoordinates($parking) {
        $coordinates = [
            'BETARRAGANE' => [ 'lat'=>'43.22072','lon'=>'-2.73078' ],
            'ELIZONDO' => [ 'lat'=>'43.21693','lon'=>'-2.73557' ],
            'IBAIZABAL' => [ 'lat'=>'43.21962','lon'=>'-2.73480' ],
            'IXER' => [ 'lat'=>'43.22223','lon'=>'-2.73462' ],
            'NAFARROA' => [ 'lat'=>'43.21595','lon'=>'-2.73008' ],
            'ZUBIONDO' => [ 'lat'=>'43.21859','lon'=>'-2.73337' ],
        ];
        return $coordinates[$parking];
    }
}