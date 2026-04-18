<?php

namespace App\Controller;

use App\Entity\DocumentItem;
use App\Entity\PoUser;
use App\Repository\DocumentFolderRepository;
use App\Security\DocumentAccessManager;
use App\Service\DocumentStorageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class DocumentLibraryController extends AbstractController
{
    #[Route('/documents', name: 'app_document_index', methods: ['GET'])]
    public function index(
        DocumentFolderRepository $folderRepository,
        DocumentAccessManager $documentAccessManager
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof PoUser) {
            throw new AccessDeniedHttpException('Acces refuse.');
        }

        $roots = $folderRepository->findRootFoldersOrdered();
        $visibleRoots = array_values(array_filter(
            $roots,
            fn ($folder) => $documentAccessManager->canAccessFolder($user, $folder)
        ));

        return $this->render('document/index.html.twig', [
            'roots' => $visibleRoots,
            'documentAccessManager' => $documentAccessManager,
            'module' => [
                'title' => 'Bibliotheque documentaire',
                'tag' => 'DOCUMENTS',
            ],
            'moduleFeatures' => [],
        ]);
    }

    #[Route('/documents/file/{id}/download', name: 'app_document_file_download', methods: ['GET'])]
    public function download(
        DocumentItem $item,
        DocumentAccessManager $documentAccessManager,
        DocumentStorageService $documentStorageService
    ): Response {
        $folder = $item->getFolder();
        if (null === $folder) {
            throw $this->createNotFoundException('Dossier introuvable.');
        }

        $user = $this->getUser();
        if (!$user instanceof PoUser || !$documentAccessManager->canAccessFolder($user, $folder)) {
            throw new AccessDeniedHttpException('Vous n avez pas les droits pour consulter ce fichier.');
        }

        if (!$item->isUploadedFile() || null === $item->getStoredFilename()) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $absolutePath = $documentStorageService->getAbsolutePath($item->getStoredFilename());
        if (null === $absolutePath || !is_file($absolutePath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($absolutePath);
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $item->getOriginalFilename() ?: basename($absolutePath)
            )
        );

        if (null !== $item->getMimeType()) {
            $response->headers->set('Content-Type', $item->getMimeType());
        }

        return $response;
    }
}
