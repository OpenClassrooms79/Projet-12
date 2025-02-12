<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController{
    #[Route('/user', name: 'app_user')]
    public function index(): Response
    {
        return $this->json([
            'message' => 'welcome to your new controller!',
            'path' => 'src/Controller/UserController.php',
        ]);
    }
}
