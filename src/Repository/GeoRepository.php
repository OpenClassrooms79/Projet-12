<?php

namespace App\Repository;

use App\Entity\Geo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

use function file_get_contents;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * @extends ServiceEntityRepository<Geo>
 */
class GeoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly TranslatorInterface $translator)
    {
        parent::__construct($registry, Geo::class);
    }

    public function getCoordinatesFromCity(string $city): ?Geo
    {
        // vérifier si les infos ne sont pas déjà en cache
        $geo = $this->findOneBy(['name' => $city]);

        if ($geo === null) {
            try {
                $geo_result = json_decode(
                    file_get_contents(
                        $this->translator->trans(
                            $_ENV['GEO_COORDINATES_URL'],
                            [
                                '{API_KEY}' => $_ENV['OPENWEATHERMAP_API_KEY'],
                                '{CITY}' => $city,
                            ],
                        ),
                    ),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                )[0];
                // mettre en cache les infos
                $geo = new Geo();
                $geo->setCountryCode($geo_result['country']);

                if ($geo_result['local_names']['fr']) {
                    // si le nom en français existe, on utilise celui-ci
                    $geo->setName($geo_result['local_names']['fr']);
                } else {
                    // sinon on utilise le nom par défaut
                    $geo->setName($geo_result['name']);
                }

                $geo->setLatitude($geo_result['lat']);
                $geo->setLongitude($geo_result['lon']);
                $this->getEntityManager()->persist($geo);
                $this->getEntityManager()->flush();
            } catch (Exception) {
                return null;
            }
        }

        return $geo;
    }
}
