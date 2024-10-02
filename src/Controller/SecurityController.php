<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['POST'])]
    public function login(#[CurrentUser] ?User $user): Response
    {
        return $this->json([
            'user'  => $user->getUserIdentifier(),
        ]);
    }
}
