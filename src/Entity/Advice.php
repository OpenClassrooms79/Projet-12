<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Controller\AdviceController;
use App\Repository\AdviceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdviceRepository::class)]
#[ApiResource(
    description: 'test de description globale',
    operations: [
        new Get(
            uriTemplate: '/api/conseil',
            routeName: 'advice_current_month',
            controller: AdviceController::class,
            description: 'test de description pour une route spécifique',
            extraProperties: [
                'openapi' => [
                    'summary' => 'Récupération d\'une entité',
                    'description' => 'Permet de récupérer une entité spécifique par son identifiant.',
                ],
            ],
        ),
        new Get(
            uriTemplate: '/api/conseil/{month}',
            routeName: 'advice_specific_month',
            controller: AdviceController::class,
        ),
        new Post(
            uriTemplate: '/api/conseil/{months}/{detail}',
            routeName: 'advice_add',
            controller: AdviceController::class,
            description: 'Ajoute un nouveau conseil',
        ),
        new Put(
            uriTemplate: '/api/conseil/{id}/{months}',
            routeName: 'advice_update',
            controller: AdviceController::class,
            description: 'Met à jour la liste des mois du conseil $id',
        ),
        new Put(
            uriTemplate: '/api/conseil/{id}/{months}/{detail}',
            routeName: 'advice_update2',
            controller: AdviceController::class,
            description: 'Met à jour la liste des mois du conseil $id, et éventuellement le détail',
        ),
        new Delete(
            uriTemplate: '/api/conseil/{id}',
            routeName: 'advice_delete',
            controller: AdviceController::class,
            description: 'Supprime le conseil $id',
        ),
    ]
)]
class Advice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10000)]
    private ?string $detail = null;

    /**
     * @var Collection<int, Month>
     */
    #[ORM\ManyToMany(targetEntity: Month::class, inversedBy: 'advice')]
    private Collection $months;

    public function __construct()
    {
        $this->months = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function setDetail(string $detail): static
    {
        $this->detail = $detail;

        return $this;
    }

    /**
     * @return Collection<int, Month>
     */
    public function getMonths(): Collection
    {
        return $this->months;
    }

    public function addMonth(Month $month): static
    {
        if (!$this->months->contains($month)) {
            $this->months->add($month);
        }

        return $this;
    }

    public function removeMonth(Month $month): static
    {
        $this->months->removeElement($month);

        return $this;
    }
}
