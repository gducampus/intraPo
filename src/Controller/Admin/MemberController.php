<?php

namespace App\Controller\Admin;

use App\Entity\Member;
use App\Form\MemberImportType;
use App\Form\MemberType;
use App\Repository\MemberRepository;
use App\Service\MemberImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/member')]
#[IsGranted('ROLE_ADMIN')]
final class MemberController extends AbstractController
{
    #[Route(name: 'app_member_index', methods: ['GET'])]
    public function index(Request $request, MemberRepository $memberRepository): Response
    {
        $perPage = 15;
        $page = max(1, $request->query->getInt('page', 1));
        $totalMembers = $memberRepository->countAll();
        $totalPages = max(1, (int) ceil($totalMembers / $perPage));
        $page = min($page, $totalPages);

        $members = $memberRepository->findPaginated($page, $perPage);
        $startItem = $totalMembers > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $endItem = $totalMembers > 0 ? min($page * $perPage, $totalMembers) : 0;

        $importForm = $this->createForm(MemberImportType::class, null, [
            'action' => $this->generateUrl('app_member_import'),
            'method' => 'POST',
        ]);

        return $this->render('admin/member/index.html.twig', [
            'members' => $members,
            'importForm' => $importForm,
            'page' => $page,
            'perPage' => $perPage,
            'totalMembers' => $totalMembers,
            'totalPages' => $totalPages,
            'startItem' => $startItem,
            'endItem' => $endItem,
        ]);
    }

    #[Route('/import', name: 'app_member_import', methods: ['POST'])]
    public function import(Request $request, MemberImportService $memberImportService): Response
    {
        $form = $this->createForm(MemberImportType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            if ($file) {
                try {
                    $report = $memberImportService->importFromXlsx($file->getPathname());
                    $this->addFlash('success', sprintf(
                        'Import termine: %d cree(s), %d mis a jour, %d ignore(s), %d secteur(s) cree(s).',
                        $report->created,
                        $report->updated,
                        $report->skipped,
                        $report->createdSectors
                    ));
                } catch (\Throwable $exception) {
                    $this->addFlash('error', sprintf('Erreur pendant l\'import: %s', $exception->getMessage()));
                }
            }
        } else {
            $this->addFlash('error', 'Le fichier importe est invalide.');
        }

        return $this->redirectToRoute('app_member_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/map', name: 'app_member_map', methods: ['GET'])]
    public function map(MemberRepository $memberRepository): Response
    {
        $memberPoints = [];

        foreach ($memberRepository->findAllWithCoordinates() as $member) {
            $fullName = trim(implode(' ', array_filter([
                $member->getShortTitle(),
                $member->getLastNameOrCompany(),
                $member->getFirstNameOrService(),
            ], static fn (?string $part): bool => null !== $part && trim($part) !== '')));

            if ($fullName === '') {
                $fullName = $member->getPreferredEmail() ?: sprintf('Membre #%d', $member->getId() ?? 0);
            }

            $memberPoints[] = [
                'id' => $member->getId(),
                'name' => $fullName,
                'lastName' => $member->getLastNameOrCompany(),
                'firstName' => $member->getFirstNameOrService(),
                'lat' => $member->getLatitude(),
                'lng' => $member->getLongitude(),
                'city' => $member->getCity(),
                'address' => $member->getAddress(),
                'sector' => $member->getSector()?->getName(),
                'email' => $member->getPreferredEmail(),
                'phone' => $member->getMobilePhone() ?: $member->getHomePhone(),
            ];
        }

        return $this->render('admin/member/map.html.twig', [
            'memberPoints' => $memberPoints,
        ]);
    }

    #[Route('/new', name: 'app_member_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $member = new Member();
        $form = $this->createForm(MemberType::class, $member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($member);
            $entityManager->flush();

            return $this->redirectToRoute('app_member_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/member/new.html.twig', [
            'member' => $member,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_member_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Member $member, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MemberType::class, $member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_member_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/member/edit.html.twig', [
            'member' => $member,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_member_delete', methods: ['POST'])]
    public function delete(Request $request, Member $member, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$member->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($member);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_member_index', [], Response::HTTP_SEE_OTHER);
    }
}
