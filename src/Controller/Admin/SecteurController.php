<?php

namespace App\Controller\Admin;

use App\Entity\Secteur;
use App\Form\SecteurType;
use App\Repository\SecteurRepository;
use App\Service\SectorNameNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/secteur')]
#[IsGranted('ROLE_ADMIN')]
final class SecteurController extends AbstractController
{
    public function __construct(
        private readonly SectorNameNormalizer $sectorNameNormalizer
    ) {
    }

    #[Route('', name: 'app_secteur_index', methods: ['GET'])]
    public function index(SecteurRepository $secteurRepository): Response
    {
        return $this->render('admin/secteur/index.html.twig', [
            'secteurs' => $secteurRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_secteur_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $secteur = new Secteur();
        $form = $this->createForm(SecteurType::class, $secteur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $duplicate = $this->findDuplicateSector($entityManager, $secteur);
            if (null !== $duplicate) {
                $form->get('name')->addError(new FormError(sprintf('Un secteur similaire existe deja: "%s".', $duplicate->getName())));
            } else {
                $entityManager->persist($secteur);
                $entityManager->flush();

                return $this->redirectToRoute('app_secteur_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/secteur/new.html.twig', [
            'secteur' => $secteur,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_secteur_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Secteur $secteur, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SecteurType::class, $secteur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $duplicate = $this->findDuplicateSector($entityManager, $secteur);
            if (null !== $duplicate) {
                $form->get('name')->addError(new FormError(sprintf('Un secteur similaire existe deja: "%s".', $duplicate->getName())));
            } else {
                $entityManager->flush();

                return $this->redirectToRoute('app_secteur_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/secteur/edit.html.twig', [
            'secteur' => $secteur,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_secteur_delete', methods: ['POST'])]
    public function delete(Request $request, Secteur $secteur, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$secteur->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($secteur);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_secteur_index', [], Response::HTTP_SEE_OTHER);
    }

    private function findDuplicateSector(EntityManagerInterface $entityManager, Secteur $current): ?Secteur
    {
        $normalizedCurrent = $this->sectorNameNormalizer->normalize($current->getName());
        if ($normalizedCurrent === '') {
            return null;
        }

        /** @var Secteur[] $all */
        $all = $entityManager->getRepository(Secteur::class)->findAll();
        foreach ($all as $sector) {
            if (null !== $current->getId() && $sector->getId() === $current->getId()) {
                continue;
            }

            if ($this->sectorNameNormalizer->normalize($sector->getName()) === $normalizedCurrent) {
                return $sector;
            }
        }

        return null;
    }
}
