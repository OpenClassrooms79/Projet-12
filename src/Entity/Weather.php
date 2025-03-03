<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\WeatherRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WeatherRepository::class)]
#[ApiResource]
class Weather
{
    public const CACHE_DURATION = 14400; // durée de conservation (en secondes) des données météo en cache

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'weather')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Geo $geo = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $date = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGeo(): ?Geo
    {
        return $this->geo;
    }

    public function setGeo(?Geo $geo): static
    {
        $this->geo = $geo;

        return $this;
    }

    public function getDate(): ?DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }
}
