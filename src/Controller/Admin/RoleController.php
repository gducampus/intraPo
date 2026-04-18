<?php

namespace App\Controller\Admin;

use App\Entity\PoUser;
use App\Entity\Role;
use App\Form\RoleType;
use App\Repository\PoUserRepository;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/role')]
#[IsGranted('ROLE_ADMIN')]
final class RoleController extends AbstractController
{
    #[Route(name: 'app_role_index', methods: ['GET'])]
    public function index(RoleRepository $roleRepository, PoUserRepository $poUserRepository): Response
    {
        $roles = $roleRepository->findAllOrderedByLabel();

        return $this->render('admin/role/index.html.twig', [
            'roles' => $roles,
            'usageCounts' => $this->buildUsageCounts($roles, $poUserRepository->findAll()),
        ]);
    }

    #[Route('/new', name: 'app_role_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $role = new Role();
        $form = $this->createForm(RoleType::class, $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($role);
            $entityManager->flush();

            $this->addFlash('success', 'Role cree avec succes.');

            return $this->redirectToRoute('app_role_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/role/new.html.twig', [
            'role' => $role,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_role_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Role $role,
        EntityManagerInterface $entityManager,
        PoUserRepository $poUserRepository
    ): Response {
        $oldCode = (string) $role->getCode();

        $form = $this->createForm(RoleType::class, $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newCode = (string) $role->getCode();

            if ($oldCode !== $newCode && $this->isProtectedRoleCode($oldCode)) {
                $form->get('code')->addError(new FormError('Ce role systeme ne peut pas changer de code.'));
            }

            if ($form->isValid()) {
                $updatedUsers = $this->replaceRoleCodeOnUsers($poUserRepository->findAll(), $oldCode, $newCode);
                $entityManager->flush();

                if ($updatedUsers > 0) {
                    $this->addFlash('success', sprintf('Role mis a jour. %d compte(s) utilisateur ajuste(s).', $updatedUsers));
                } else {
                    $this->addFlash('success', 'Role mis a jour.');
                }

                return $this->redirectToRoute('app_role_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/role/edit.html.twig', [
            'role' => $role,
            'form' => $form,
            'isProtected' => $this->isProtectedRoleCode((string) $role->getCode()),
        ]);
    }

    #[Route('/{id}', name: 'app_role_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Role $role,
        PoUserRepository $poUserRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('delete'.$role->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_role_index', [], Response::HTTP_SEE_OTHER);
        }

        $code = (string) $role->getCode();

        if ($this->isProtectedRoleCode($code)) {
            $this->addFlash('error', 'Ce role systeme ne peut pas etre supprime.');
            return $this->redirectToRoute('app_role_index', [], Response::HTTP_SEE_OTHER);
        }

        $usageCount = $this->countUsersWithRole($poUserRepository->findAll(), $code);
        if ($usageCount > 0) {
            $this->addFlash('error', sprintf('Suppression impossible: ce role est utilise par %d compte(s).', $usageCount));
            return $this->redirectToRoute('app_role_index', [], Response::HTTP_SEE_OTHER);
        }

        $entityManager->remove($role);
        $entityManager->flush();

        $this->addFlash('success', 'Role supprime.');

        return $this->redirectToRoute('app_role_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * @param Role[] $roles
     * @param PoUser[] $users
     * @return array<string, int>
     */
    private function buildUsageCounts(array $roles, array $users): array
    {
        $counts = [];
        foreach ($roles as $role) {
            $code = (string) $role->getCode();
            $counts[$code] = 0;
        }

        foreach ($users as $user) {
            foreach (array_keys($counts) as $roleCode) {
                if ($this->userHasRole($user, $roleCode)) {
                    ++$counts[$roleCode];
                }
            }
        }

        return $counts;
    }

    /**
     * @param PoUser[] $users
     */
    private function countUsersWithRole(array $users, string $code): int
    {
        $count = 0;

        foreach ($users as $user) {
            if ($this->userHasRole($user, $code)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param PoUser[] $users
     */
    private function replaceRoleCodeOnUsers(array $users, string $oldCode, string $newCode): int
    {
        if ($oldCode === $newCode) {
            return 0;
        }

        $updatedUsers = 0;

        foreach ($users as $user) {
            $storedRoles = $this->extractStoredRoles($user);
            if (!in_array($oldCode, $storedRoles, true)) {
                continue;
            }

            $updatedRoles = array_map(
                static fn (string $roleCode): string => $roleCode === $oldCode ? $newCode : $roleCode,
                $storedRoles
            );
            $updatedRoles = array_values(array_unique($updatedRoles));

            $user->setRoles($updatedRoles);
            ++$updatedUsers;
        }

        return $updatedUsers;
    }

    /**
     * @return string[]
     */
    private function extractStoredRoles(PoUser $user): array
    {
        return array_values(array_filter(
            $user->getRoles(),
            static fn (string $role): bool => $role !== 'ROLE_USER'
        ));
    }

    private function isProtectedRoleCode(string $code): bool
    {
        return in_array($code, ['ROLE_ADMIN', 'ROLE_USER', 'ROLE_MODULE_ALL'], true);
    }

    private function userHasRole(PoUser $user, string $code): bool
    {
        if ($code === 'ROLE_USER') {
            return true;
        }

        return in_array($code, $this->extractStoredRoles($user), true);
    }
}
