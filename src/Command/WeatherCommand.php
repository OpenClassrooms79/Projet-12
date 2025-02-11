<?php

namespace App\Command;

use App\Entity\Geo;
use App\Repository\GeoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

use function file_get_contents;
use function flush;
use function json_decode;
use function print_r;

use const JSON_THROW_ON_ERROR;

#[AsCommand(
    name: 'weather',
    description: "Renvoie les informations météo d'une commune à partir de son code postal",
)]
class WeatherCommand extends Command
{
    public function __construct(private readonly TranslatorInterface $translator, private GeoRepository $geoRepository, private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('code_postal', InputArgument::REQUIRED, 'Code postal de la commune')
            ->addArgument('code_pays', InputArgument::REQUIRED, 'Code du pays sur 2 lettres');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $post_code = $input->getArgument('code_postal');
        $country_code = $input->getArgument('code_pays');

        // vérifier si les infos ne sont pas déjà en cache
        $geo = $this->geoRepository->findOneBy(['postCode' => $post_code, 'countryCode' => $country_code]);
        if ($geo !== null) {
            $geo_result = [
                'lat' => $geo->getLatitude(),
                'lon' => $geo->getLongitude(),
            ];
        } else {
            try {
                $geo_result = json_decode(
                    file_get_contents(
                        $this->translator->trans(
                            $_ENV['GEO_COORDINATES_URL'],
                            [
                                '{API_KEY}' => $_ENV['OPENWEATHERMAP_API_KEY'],
                                '{POST_CODE}' => $post_code,
                                '{COUNTRY_CODE}' => $country_code,
                            ],
                        ),
                    ),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
            } catch (\Exception $e) {
                $io->error($e->getMessage());
                return Command::FAILURE;
            }

            // mettre en cache les infos
            $geo = new Geo();
            $geo->setPostCode($post_code);
            $geo->setCountryCode($country_code);
            $geo->setName($geo_result['name']);
            $geo->setLatitude($geo_result['lat']);
            $geo->setLongitude($geo_result['lon']);
            $this->entityManager->persist($geo);
            $this->entityManager->flush();
        }
        print_r($geo_result);

        try {
            $weather_result = json_decode(
                file_get_contents(
                    $this->translator->trans(
                        $_ENV['CURRENT_WEATHER_URL'],
                        [
                            '{API_KEY}' => $_ENV['OPENWEATHERMAP_API_KEY'],
                            '{LATITUDE}' => $geo_result['lat'],
                            '{LONGITUDE}' => $geo_result['lon'],
                            '{LANGUAGE_CODE}' => $country_code,
                        ],
                    ),
                ),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        print_r($weather_result);

        //$output->writeln($weather_result);
        // TODO mettre en cache les résultats

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
