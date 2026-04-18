<?php

namespace App\Controller\Admin;

use App\Entity\ApplicationFeature;
use App\Form\ApplicationFeatureType;
use App\Repository\ApplicationFeatureRepository;
use App\Service\FeatureCatalogSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/feature')]
#[IsGranted('ROLE_ADMIN')]
final class ApplicationFeatureController extends AbstractController
{
    #[Route(name: 'app_feature_index', methods: ['GET'])]
    public function index(
        ApplicationFeatureRepository $featureRepository,
        FeatureCatalogSynchronizer $featureCatalogSynchronizer
    ): Response {
        $syncedCount = $featureCatalogSynchronizer->sync();
        if ($syncedCount > 0) {
            $this->addFlash('success', sprintf('%d fonctionnalite(s) ajoutee(s) automatiquement au catalogue.', $syncedCount));
        }

        return $this->render('admin/application_feature/index.html.twig', [
            'features' => $featureRepository->findBy([], ['label' => 'ASC']),
            'syncedCount' => $syncedCount,
        ]);
    }

    #[Route('/sync', name: 'app_feature_sync', methods: ['POST'])]
    public function sync(Request $request, FeatureCatalogSynchronizer $featureCatalogSynchronizer): Response
    {
        if ($this->isCsrfTokenValid('sync_catalog', $request->getPayload()->getString('_token'))) {
            $count = $featureCatalogSynchronizer->sync();
            $this->addFlash('success', sprintf('Synchronisation terminee: %d fonctionnalite(s) ajoutee(s).', $count));
        }

        return $this->redirectToRoute('app_feature_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/new', name: 'app_feature_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $feature = new ApplicationFeature();
        $form = $this->createForm(ApplicationFeatureType::class, $feature);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($feature);
            $entityManager->flush();

            return $this->redirectToRoute('app_feature_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/application_feature/new.html.twig', [
            'feature' => $feature,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_feature_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ApplicationFeature $feature, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ApplicationFeatureType::class, $feature);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_feature_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/application_feature/edit.html.twig', [
            'feature' => $feature,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_feature_delete', methods: ['POST'])]
    public function delete(Request $request, ApplicationFeature $feature, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$feature->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($feature);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_feature_index', [], Response::HTTP_SEE_OTHER);
    }
}

