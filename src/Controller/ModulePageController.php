<?php

namespace App\Controller;

use App\Entity\Module;
use App\Entity\PoUser;
use App\Form\ModuleType;
use App\Repository\ApplicationFeatureRepository;
use App\Repository\ModuleRepository;
use App\Security\ModuleAccessManager;
use App\Service\FeatureCatalogSynchronizer;
use App\Service\ModuleEditorHelper;
use App\Service\ModuleFeatureLinkResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ModulePageController extends AbstractController
{
    #[Route('/module/{id}', name: 'app_module_page', methods: ['GET', 'POST'])]
    public function show(
        Request $request,
        Module $module,
        ModuleAccessManager $moduleAccessManager,
        ModuleFeatureLinkResolver $featureLinkResolver,
        FeatureCatalogSynchronizer $featureCatalogSynchronizer,
        ApplicationFeatureRepository $featureRepository,
        ModuleRepository $moduleRepository,
        ModuleEditorHelper $moduleEditorHelper,
        EntityManagerInterface $entityManager
    ): Response {
        $canManageModule = $this->isGranted('ROLE_ADMIN');
        if (!$module->isAvailable() && !$canManageModule) {
            throw $this->createNotFoundException('Ce module n\'est pas disponible.');
        }

        $user = $this->getUser();
        if (
            !$canManageModule
            && (!$user instanceof PoUser || !$moduleAccessManager->canAccessModule($user, $module))
        ) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour acceder a ce module.');
        }

        $form = null;
        $featureCatalogData = [];
        if ($canManageModule) {
            $featureCatalogSynchronizer->sync();

            $form = $this->createForm(ModuleType::class, $module);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $moduleEditorHelper->syncModuleFeatures(
                    $module,
                    $moduleEditorHelper->sanitizeFeatureIds($request->request->all('selectedFeatures')),
                    $featureRepository,
                    $entityManager
                );
                $entityManager->flush();
                $this->addFlash('success', 'Le module a ete mis a jour.');

                return $this->redirectToRoute('app_module_page', ['id' => $module->getId()], Response::HTTP_SEE_OTHER);
            }

            $submittedFeatureIds = $form->isSubmitted()
                ? $moduleEditorHelper->sanitizeFeatureIds($request->request->all('selectedFeatures'))
                : null;

            $featureCatalogData = $moduleEditorHelper->buildFeatureCatalogData(
                $module,
                $featureRepository,
                $submittedFeatureIds
            );
        }

        $moduleFeatureLinks = [];
        foreach ($module->getModuleFeatures() as $moduleFeature) {
            $moduleFeatureId = $moduleFeature->getId();
            $feature = $moduleFeature->getFeature();
            if ($moduleFeatureId === null || $feature === null) {
                continue;
            }

            $resolvedLink = $featureLinkResolver->resolve($feature);
            if ($resolvedLink !== null) {
                $moduleFeatureLinks[$moduleFeatureId] = $resolvedLink;
            }
        }

        if ($canManageModule) {
            $accessibleModules = $moduleRepository->findBy([], ['title' => 'ASC']);
        } else {
            $availableModules = $moduleRepository->findBy(['isAvailable' => true], ['title' => 'ASC']);
            $accessibleModules = $moduleAccessManager->filterAccessibleModules($user, $availableModules);
        }

        return $this->render('module/show.html.twig', [
            'module' => $module,
            'moduleFeatures' => $module->getModuleFeatures(),
            'moduleFeatureLinks' => $moduleFeatureLinks,
            'accessibleModules' => $accessibleModules,
            'canManageModule' => $canManageModule,
            'managementForm' => $form?->createView(),
            'moduleExpectedRoles' => $moduleAccessManager->expectedRoles($module),
            ...$featureCatalogData,
        ]);
    }
}
