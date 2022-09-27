<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\DTO;

/**
 * Description of ParkingOcupationLine.
 *
 * @author ibilbao
 */
class ParkingOccupationLineDTO
{
    private $id;
    private $nombre;
    private $aforo;
    private $ocupacion;
    private $libre;

    public function getId()
    {
        return $this->id;
    }

    public function getNombre()
    {
        return $this->nombre;
    }

    public function getAforo()
    {
        return $this->aforo;
    }

    public function getOcupacion()
    {
        return $this->ocupacion;
    }

    public function getLibre()
    {
        return $this->libre;
    }

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    public function setNombre($nombre)
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function setAforo($aforo)
    {
        $this->aforo = $aforo;

        return $this;
    }

    public function setOcupacion($ocupacion)
    {
        $this->ocupacion = $ocupacion;

        return $this;
    }

    public function setLibre($libre)
    {
        $this->libre = $libre;

        return $this;
    }

    public static function createParkingOcupationFromData(array $data)
    {
         $pol = new self();
         $pol->setId($data['id']);
         $pol->setNombre($data['name']);
         $pol->setAforo(intval($data['capacity']));
         $pol->setOcupacion($data['occupation']);
         $pol->setLibre(($data['capacity'] - $data['occupation']) <= 0 ? 0 : $data['capacity'] - $data['occupation']);

        return $pol;
    }
}