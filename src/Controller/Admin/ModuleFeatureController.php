<?php

namespace App\Controller\Admin;

use App\Entity\ModuleFeature;
use App\Repository\ApplicationFeatureRepository;
use App\Repository\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/module-feature')]
#[IsGranted('ROLE_ADMIN')]
final class ModuleFeatureController extends AbstractController
{
    #[Route('/assign', name: 'app_module_feature_assign', methods: ['POST'])]
    public function assign(
        Request $request,
        ModuleRepository $moduleRepository,
        ApplicationFeatureRepository $featureRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $moduleId = $request->request->getInt('moduleId');
        $featureId = $request->request->getInt('featureId');

        $module = $moduleRepository->find($moduleId);
        $feature = $featureRepository->find($featureId);

        if ($module && $feature && $this->isCsrfTokenValid('assign'.$moduleId, $request->request->getString('_token'))) {
            $pivot = new ModuleFeature();
            $pivot->setModule($module);
            $pivot->setFeature($feature);
            $pivot->setPosition($module->getModuleFeatures()->count());
            $entityManager->persist($pivot);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_module_page', ['id' => $moduleId], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/remove', name: 'app_module_feature_remove', methods: ['POST'])]
    public function remove(
        Request $request,
        ModuleFeature $moduleFeature,
        EntityManagerInterface $entityManager
    ): Response {
        $moduleId = $moduleFeature->getModule()->getId();

        if ($this->isCsrfTokenValid('remove'.$moduleFeature->getId(), $request->request->getString('_token'))) {
            $entityManager->remove($moduleFeature);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_module_page', ['id' => $moduleId], Response::HTTP_SEE_OTHER);
    }
}
