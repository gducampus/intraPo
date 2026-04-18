<?php

namespace App\Controller;

use App\Repository\ModuleRepository;
use App\Security\ModuleAccessManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(ModuleRepository $moduleRepository, ModuleAccessManager $moduleAccessManager): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->render('home/index.html.twig', [
                'menuSections' => [
                    [
                        'title' => 'Suivi pastoral',
                        'description' => 'Gestion des membres, de la carte et des secteurs.',
                        'icon' => 'users-round',
                        'links' => [
                            ['label' => 'Liste des membres', 'route' => 'app_member_index'],
                            ['label' => 'Carte des membres', 'route' => 'app_member_map'],
                            ['label' => 'Secteurs', 'route' => 'app_secteur_index'],
                        ],
                    ],
                    [
                        'title' => 'Gestions documents',
                        'description' => 'Acces rapide a la bibliotheque documentaire.',
                        'icon' => 'folders',
                        'links' => [
                            ['label' => 'Bibliotheque documentaire', 'route' => 'app_document_admin_index'],
                        ],
                    ],
                    [
                        'title' => 'Administration',
                        'description' => 'Configuration des modules, des comptes et des droits.',
                        'icon' => 'settings-2',
                        'links' => [
                            ['label' => 'Gestion des modules', 'route' => 'app_module_index'],
                            ['label' => 'Catalogue des fonctionnalites', 'route' => 'app_feature_index'],
                            ['label' => 'Comptes utilisateurs', 'route' => 'app_po_user_index'],
                            ['label' => 'Roles', 'route' => 'app_role_index'],
                            ['label' => 'Historique des connexions', 'route' => 'app_login_history_index'],
                            ['label' => 'Appareils autorises', 'route' => 'app_trusted_device_index'],
                        ],
                    ],
                ],
            ]);
        }

        $availableModules = $moduleRepository->findBy(['isAvailable' => true], ['title' => 'ASC']);
        $authorizedModules = $moduleAccessManager->filterAccessibleModules($this->getUser(), $availableModules);
        $firstModule = $authorizedModules[0] ?? null;
        if ($firstModule !== null) {
            return $this->redirectToRoute('app_module_page', ['id' => $firstModule->getId()]);
        }

        return $this->render('home/empty.html.twig');
    }
}
