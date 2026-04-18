<?php

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

class SecurityController
{
    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): void
    {
        throw new \LogicException('Symfony gere le logout automatiquement.');
    }
}
