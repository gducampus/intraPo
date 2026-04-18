<?php

namespace App\Controller\Admin;

use App\Entity\Module;
use App\Form\ModuleType;
use App\Repository\ApplicationFeatureRepository;
use App\Repository\ModuleRepository;
use App\Service\FeatureCatalogSynchronizer;
use App\Service\ModuleEditorHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('admin/module')]
#[IsGranted('ROLE_ADMIN')]
final class ModuleController extends AbstractController
{
    #[Route(name: 'app_module_index', methods: ['GET'])]
    public function index(ModuleRepository $moduleRepository): Response
    {
        return $this->render('admin/module/index.html.twig', [
            'modules' => $moduleRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_module_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ApplicationFeatureRepository $featureRepository,
        FeatureCatalogSynchronizer $featureCatalogSynchronizer,
        ModuleEditorHelper $moduleEditorHelper
    ): Response {
        $featureCatalogSynchronizer->sync();

        $module = new Module();
        $form = $this->createForm(ModuleType::class, $module);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($module);
            $moduleEditorHelper->syncModuleFeatures(
                $module,
                $moduleEditorHelper->sanitizeFeatureIds($request->request->all('selectedFeatures')),
                $featureRepository,
                $entityManager
            );
            $entityManager->flush();

            return $this->redirectToRoute('app_module_index', [], Response::HTTP_SEE_OTHER);
        }

        $submittedFeatureIds = $form->isSubmitted()
            ? $moduleEditorHelper->sanitizeFeatureIds($request->request->all('selectedFeatures'))
            : null;

        return $this->render('admin/module/new.html.twig', array_merge([
            'module' => $module,
            'form' => $form,
        ], $moduleEditorHelper->buildFeatureCatalogData($module, $featureRepository, $submittedFeatureIds)));
    }

    #[Route('/{id}', name: 'app_module_show', methods: ['GET'])]
    public function show(Module $module): Response
    {
        return $this->redirectToRoute('app_module_page', ['id' => $module->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'app_module_edit', methods: ['GET'])]
    public function edit(Module $module): Response
    {
        return $this->redirectToRoute('app_module_page', ['id' => $module->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_module_delete', methods: ['POST'])]
    public function delete(Request $request, Module $module, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$module->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($module);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_module_index', [], Response::HTTP_SEE_OTHER);
    }
}
