<?php

namespace App\Controller\Admin;

use App\Entity\PoUser;
use App\Form\PoUserType;
use App\Repository\PoUserRepository;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/user')]
#[IsGranted('ROLE_ADMIN')]
final class PoUserController extends AbstractController
{
    #[Route(name: 'app_po_user_index', methods: ['GET'])]
    public function index(PoUserRepository $poUserRepository, RoleRepository $roleRepository): Response
    {
        return $this->render('admin/po_user/index.html.twig', [
            'users' => $poUserRepository->findBy([], ['email' => 'ASC']),
            'rolesCatalog' => $roleRepository->findAllOrderedByLabel(),
        ]);
    }

    #[Route('/new', name: 'app_po_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = new PoUser();
        $form = $this->createForm(PoUserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $technicalPassword = bin2hex(random_bytes(32));
            $user->setPassword($passwordHasher->hashPassword($user, $technicalPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Compte utilisateur cree avec succes.');

            return $this->redirectToRoute('app_po_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/po_user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_po_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        PoUser $user,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(PoUserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Compte utilisateur mis a jour.');

            return $this->redirectToRoute('app_po_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/po_user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_po_user_delete', methods: ['POST'])]
    public function delete(Request $request, PoUser $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->getUser() instanceof PoUser && $this->getUser()->getId() === $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_po_user_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
            $this->addFlash('success', 'Compte utilisateur supprime.');
        }

        return $this->redirectToRoute('app_po_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
