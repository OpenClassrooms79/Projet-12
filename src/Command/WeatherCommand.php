<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

use function file_get_contents;
use function json_decode;
use function print_r;

use const JSON_THROW_ON_ERROR;

#[AsCommand(
    name: 'weather',
    description: "Renvoie les informations météo d'une commune à partir de son code postal",
)]
class WeatherCommand extends Command
{
    public function __construct(private TranslatorInterface $translator)
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

        // TODO vérifier si les infos ne sont pas déjà en cache
        try {
            $geo_result = json_decode(
                file_get_contents(
                    $this->translator->trans(
                        $_ENV['OPENWEATHERMAP_GEO_URL'],
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

        print_r($geo_result);

        try {
            $weather_result = json_decode(
                file_get_contents(
                    $this->translator->trans(
                        $_ENV['OPENWEATHERMAP_URL'],
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
