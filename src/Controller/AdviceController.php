<?php

namespace App\Controller;

use App\Entity\Advice;
use App\Repository\AdviceRepository;
use App\Repository\MonthRepository;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

use function array_filter;
use function count;
use function date;
use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class AdviceController extends AbstractController
{
    public function __construct(
        private readonly AdviceRepository $adviceRepository,
        private readonly MonthRepository $monthRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /*
     * renvoie la liste de tous les conseils du mois en cours
     */
    #[Route('/api/conseil', name: 'advice_current_month', methods: [Request::METHOD_GET])]
    public function index(): JsonResponse
    {
        return new JsonResponse($this->adviceRepository->getAdvicesByMonth((int) date('n')));
    }

    /**
     * renvoie la liste de tous les conseils du mois donné
     *
     * @param int $month
     * @return JsonResponse
     */
    #[Route('/api/conseil/{month}', name: 'advice_specific_month', requirements: ['month' => Requirement::POSITIVE_INT], methods: [Request::METHOD_GET])]
    public function index2(int $month): JsonResponse
    {
        if ($month < 1 || $month > 12) {
            return new JsonResponse(
                [
                    'errors' => [
                        'status' => Response::HTTP_BAD_REQUEST,
                        'code' => 'invalid_request',
                        'source' => ['parameter' => 'month'],
                        'title' => 'Valeur incorrecte',
                        'detail' => 'Le numéro du mois doit être entre 1 et 12',
                    ],
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        return new JsonResponse($this->adviceRepository->getAdvicesByMonth($month));
    }

    /**
     * ajoute un nouveau conseil pour le(s) mois(s) donné(s)
     *
     * @param Request $request
     * @return JsonResponse
     * @throws JsonException
     */
    #[Route('/api/conseil', name: 'advice_add', methods: [Request::METHOD_POST])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['months'], $data['detail'])) {
            return new JsonResponse(
                [
                    'errors' => [
                        'status' => Response::HTTP_BAD_REQUEST,
                        'code' => 'invalid_request',
                        'source' => ['parameter' => ['months', 'detail']],
                        'title' => 'Paramètre manquant',
                        'detail' => "Au moins l'un des paramètres 'months' ou 'detail' est requis",
                    ],
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (!is_array($data['months'])) {
            $data['months'] = [$data['months']];
        }
        $months_array = $data['months'];
        $detail = $data['detail'];

        $months_array = array_filter($months_array, static function ($num) {
            return $num >= 1 && $num <= 12;
        });

        if (count($months_array) === 0) {
            return new JsonResponse(
                [
                    'message' => 'Veuillez fournir au moins un numéro de mois valide.',
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $detail = mb_trim($detail);
        if ($detail === '') {
            return new JsonResponse(
                [
                    'message' => 'Veuillez fournir un conseil.',
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $advice = new Advice();
        $advice->setDetail($detail);
        $months_names = [];
        foreach ($months_array as $num) {
            $month = $this->monthRepository->getMonthByNum((int) $num);
            if ($month !== null) {
                $advice->addMonth($month);
                $months_names[] = $month->getName();
            }
        }
        $this->entityManager->persist($advice); // obligatoire pour enregistrer un nouvel objet dans la base
        $this->entityManager->flush();

        return new JsonResponse(
            [
                'months' => $months_names,
                'id' => $advice->getId(),
                'message' => 'Conseil créé',
            ],
            Response::HTTP_CREATED,
        );
    }

    /**
     * met à jour un conseil (liste des mois et/ou détail)
     *
     * @param Request $request
     * @return JsonResponse
     * @throws JsonException
     */
    #[Route('/api/conseil', name: 'advice_update', methods: [Request::METHOD_PUT])]
    public function update(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!isset($data['id'])) {
            return new JsonResponse(
                [
                    "errors" => [
                        [
                            'status' => Response::HTTP_BAD_REQUEST,
                            'code' => 'invalid_request',
                            'source' => ['parameter' => 'id'],
                            'title' => 'Paramètre manquant',
                            'detail' => "Le paramètre 'id' est obligatoire.",
                        ],
                    ],
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        // vérification de l'existence du conseil à mettre à jour
        $id = $data['id'];
        $advice = $this->adviceRepository->find($id);
        if ($advice === null) {
            return new JsonResponse(
                [
                    'id' => $id,
                    'message' => 'Conseil non trouvé',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $months_names = null;
        $missing = [];
        if (isset($data['months'])) {
            if (!is_array($data['months'])) {
                $data['months'] = [$data['months']];
            }
            $months_array = $data['months'];
            $months_array = array_filter($months_array, static function ($month) {
                return $month >= 1 && $month <= 12;
            });

            if (count($months_array) === 0) {
                return new JsonResponse(
                    [
                        'status' => Response::HTTP_BAD_REQUEST,
                        'code' => 'invalid_request',
                        'source' => ['parameter' => 'months'],
                        'title' => 'Valeur invalide',
                        'detail' => "Si les numéros de mois sont fournis, au moins l'un d'entre eux doit avoir une valeur comprise entre 1 et 12.",
                    ],
                    Response::HTTP_BAD_REQUEST,
                );
            }

            // suppression de tous les mois auxquels le conseil est actuellement associé
            $advice->getMonths()->clear();

            // ajout des nouveaux mois au conseil
            $months_names = [];
            foreach ($months_array as $num) {
                $month = $this->monthRepository->findOneBy(['num' => $num]);
                if ($month !== null) {
                    $advice->addMonth($month);
                    $months_names[] = $month->getName();
                }
            }
        } else {
            $missing[] = 'months';
        }

        // mise à jour du détail (si fourni dans la requête)
        if (isset($data['detail'])) {
            $detail = mb_trim($data['detail']);
            if ($detail === '') {
                return new JsonResponse(
                    [
                        'status' => Response::HTTP_BAD_REQUEST,
                        'code' => 'invalid_request',
                        'source' => ['parameter' => 'detail'],
                        'title' => 'Valeur invalide',
                        'detail' => 'Si le détail est fourni, ce dernier ne peut pas être vide.',
                    ],
                    Response::HTTP_BAD_REQUEST,
                );
            }

            if ($detail !== $advice->getDetail()) {
                $advice->setDetail($detail);
            }
        } else {
            $missing[] = 'detail';
        }

        if (count($missing) === 2) {
            return new JsonResponse(
                [
                    "errors" => [
                        [
                            'status' => Response::HTTP_BAD_REQUEST,
                            'code' => 'invalid_request',
                            'source' => ['parameter' => ['months', 'detail']],
                            'title' => 'Paramètre manquant',
                            'detail' => "Au moins l'un des paramètres 'months' ou 'detail' est requis",
                        ],
                    ],
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        // l'appel à persist() n'est pas obligatoire ici car toutes les modifications sont effectuées sur un objet déjà persisté
        $this->entityManager->flush();

        return new JsonResponse(
            [
                'conseil' => [
                    'id' => $advice->getId(),
                    'detail' => $advice->getDetail(),
                    'months' => $months_names ?? $this->adviceRepository->getMonthNames($advice),
                ],
                'message' => 'Conseil mis à jour',
            ],
        );
    }

    /**
     * Supprime un conseil
     *
     * @param int $id
     * @return JsonResponse
     */
    #[Route('/api/conseil/{id}', name: 'advice_delete', requirements: ['id' => Requirement::POSITIVE_INT], methods: [Request::METHOD_DELETE])]
    public function delete(int $id): JsonResponse
    {
        $advice = $this->adviceRepository->find($id);

        // vérification de l'existence du conseil à supprimer
        if ($advice === null) {
            return new JsonResponse(
                [
                    'id' => $id,
                    'message' => 'Conseil non trouvé',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $this->entityManager->remove($advice);
        $this->entityManager->flush();

        return new JsonResponse(
            [
                'id' => $id,
                'message' => 'Conseil supprimé',
            ],
        );
    }
}
