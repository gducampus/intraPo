<?php

namespace App\Controller\Admin;

use App\Repository\LoginHistoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/login-history')]
#[IsGranted('ROLE_ADMIN')]
final class LoginHistoryController extends AbstractController
{
    #[Route(name: 'app_login_history_index', methods: ['GET'])]
    public function index(LoginHistoryRepository $loginHistoryRepository): Response
    {
        return $this->render('admin/login_history/index.html.twig', [
            'histories' => $loginHistoryRepository->findLatest(300),
        ]);
    }
}

