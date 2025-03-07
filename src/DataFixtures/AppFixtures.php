<?php

namespace App\DataFixtures;

use App\Entity\Advice;
use App\Entity\Month;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker;

use Random\RandomException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function random_int;

class AppFixtures extends Fixture
{
    public const NB_USERS = 20;
    public const NB_ADVICES = 50;
    public const NB_MONTHS = 12;
    private array $months = [];
    public const PASSWORD = 'test';

    public function __construct(private readonly UserPasswordHasherInterface $userPasswordHasher) {}

    /**
     * @throws RandomException
     */
    public function load(ObjectManager $manager): void
    {
        $faker = Faker\Factory::create();

        $this->loadUsers($manager, $faker);
        $this->loadMonths($manager);
        $this->loadAdvices($manager, $faker);
    }

    public function loadUsers(ObjectManager $manager, Faker\Generator $faker): void
    {
        $cities = [
            "Paris",
            "São Paulo",
            "Baden-Baden",
            "La Coruña", // La Corogne
            "الرياض", // Riyad
            "北京", // Beijing
            "Hà Nội", // Hanoï
            "Αθήνα", // Athènes
            "京都市", // Kyoto
            "서울", // Seoul
            "กรุงเทพมหานคร", // Bangkok
            "Երևան", // Erevan
            "თბილისი", // Tbilissi
            "अनन्तपुर", // Anantapur
            "ວຽງຈັນ", // Vientiane
            "ကော့သောင်း", // Kawthoung
            "צפת", // Safed
            "Шумен", // Choumen
            "Київ", // Kiev
            "چابهار", // Chabahar
        ];
        $roles = ['ROLE_USER', 'ROLE_ADMIN'];

        for ($i = 1; $i <= self::NB_USERS; $i++) {
            $user = new User();
            $user
                ->setLogin($faker->userName())
                ->setPassword($this->userPasswordHasher->hashPassword($user, self::PASSWORD))
                ->setCity($faker->randomElement($cities))
                ->setRoles([$faker->randomElement($roles)]);
            $manager->persist($user);
        }
        $manager->flush();
    }

    public function loadMonths(ObjectManager $manager): void
    {
        $months = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        for ($i = 0; $i < self::NB_MONTHS; $i++) {
            $month = new Month();
            $month
                ->setNum($i + 1)
                ->setName($months[$i]);
            $manager->persist($month);
        }
        $manager->flush();

        $this->months = $manager->getRepository(Month::class)->findAll();
    }

    /**
     * @throws RandomException
     */
    public function loadAdvices(ObjectManager $manager, Faker\Generator $faker): void
    {
        for ($i = 1; $i <= self::NB_ADVICES; $i++) {
            $advice = new Advice();
            $advice->setDetail($faker->text(300));

            for ($n = 1; $n < random_int(1, self::NB_MONTHS); $n++) {
                $advice->addMonth($this->months[array_rand($this->months)]);
            }

            $manager->persist($advice);
        }
        $manager->flush();
    }
}
