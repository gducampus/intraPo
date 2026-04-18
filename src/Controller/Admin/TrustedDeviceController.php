<?php

namespace App\Controller\Admin;

use App\Entity\PoUser;
use App\Entity\TrustedDevice;
use App\Repository\TrustedDeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/trusted-device')]
#[IsGranted('ROLE_ADMIN')]
final class TrustedDeviceController extends AbstractController
{
    #[Route(name: 'app_trusted_device_index', methods: ['GET'])]
    public function index(TrustedDeviceRepository $trustedDeviceRepository): Response
    {
        return $this->render('admin/trusted_device/index.html.twig', [
            'pendingDevices' => $trustedDeviceRepository->findPending(300),
            'approvedDevices' => $trustedDeviceRepository->findApproved(200),
        ]);
    }

    #[Route('/{id}/approve', name: 'app_trusted_device_approve', methods: ['POST'])]
    public function approve(
        Request $request,
        TrustedDevice $trustedDevice,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('approve_device_'.$trustedDevice->getId(), $request->request->getString('_token'))) {
            $trustedDevice->setIsApproved(true);
            $trustedDevice->setApprovedAt(new \DateTimeImmutable());
            $trustedDevice->setLastSeenAt($trustedDevice->getLastSeenAt() ?? new \DateTimeImmutable());

            $admin = $this->getUser();
            if ($admin instanceof PoUser) {
                $trustedDevice->setApprovedBy($admin);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Appareil approuve.');
        }

        return $this->redirectToRoute('app_trusted_device_index');
    }

    #[Route('/{id}/reject', name: 'app_trusted_device_reject', methods: ['POST'])]
    public function reject(
        Request $request,
        TrustedDevice $trustedDevice,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('reject_device_'.$trustedDevice->getId(), $request->request->getString('_token'))) {
            $entityManager->remove($trustedDevice);
            $entityManager->flush();
            $this->addFlash('success', 'Demande d\'appareil rejetee.');
        }

        return $this->redirectToRoute('app_trusted_device_index');
    }
}
