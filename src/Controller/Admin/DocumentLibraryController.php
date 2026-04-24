<?php

namespace App\Controller\Admin;

use App\Entity\DocumentFolder;
use App\Entity\DocumentItem;
use App\Entity\DocumentTag;
use App\Form\DocumentFolderType;
use App\Form\DocumentItemType;
use App\Repository\DocumentFolderRepository;
use App\Repository\DocumentTagRepository;
use App\Repository\RoleRepository;
use App\Service\DocumentStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/document-library')]
#[IsGranted('ROLE_ADMIN')]
final class DocumentLibraryController extends AbstractController
{
    #[Route('', name: 'app_document_admin_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        DocumentFolderRepository $folderRepository,
        RoleRepository $roleRepository,
        DocumentTagRepository $tagRepository,
        EntityManagerInterface $entityManager,
        DocumentStorageService $documentStorageService
    ): Response
    {
        $currentFolder = null;
        $requestedFolderId = $request->query->getInt('folder', 0);
        if ($requestedFolderId > 0) {
            $currentFolder = $folderRepository->find($requestedFolderId);
            if (!$currentFolder instanceof DocumentFolder) {
                $this->addFlash('error', 'Le dossier demande est introuvable.');

                return $this->redirectToRoute('app_document_admin_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', '');

            if ($action === 'create_folder') {
                return $this->handleCreateFolder($request, $folderRepository, $tagRepository, $entityManager, $currentFolder);
            }

            if ($action === 'create_item') {
                return $this->handleCreateItem($request, $folderRepository, $entityManager, $documentStorageService, $currentFolder);
            }
        }

        $roles = $roleRepository->findAllOrderedByLabel();
        $tags = $tagRepository->findAllOrderedByName();

        $childFolders = $folderRepository->findBy(
            ['parent' => $currentFolder],
            ['position' => 'ASC', 'name' => 'ASC']
        );
        $items = [];
        if ($currentFolder instanceof DocumentFolder) {
            $items = $currentFolder->getItems()->toArray();
            usort($items, static function (DocumentItem $a, DocumentItem $b): int {
                $compare = $a->getPosition() <=> $b->getPosition();
                if ($compare !== 0) {
                    return $compare;
                }

                return strnatcasecmp((string) $a->getTitle(), (string) $b->getTitle());
            });
        }

        return $this->render('admin/document_library/index.html.twig', [
            'currentFolder' => $currentFolder,
            'breadcrumbs' => $this->buildBreadcrumbs($currentFolder),
            'childFolders' => $childFolders,
            'items' => $items,
            'roles' => $roles,
            'tags' => $tags,
        ]);
    }

    #[Route('/folder/new/{parent}', name: 'app_document_admin_folder_new', methods: ['GET', 'POST'], defaults: ['parent' => null])]
    public function newFolder(
        Request $request,
        EntityManagerInterface $entityManager,
        DocumentFolderRepository $folderRepository,
        DocumentTagRepository $tagRepository,
        ?DocumentFolder $parent = null
    ): Response {
        $folder = new DocumentFolder();
        $folder->setParent($parent);

        $form = $this->createForm(DocumentFolderType::class, $folder, [
            'parent_choices' => $folderRepository->findPotentialParents(),
            'use_root_access_select' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->validateFolderSecurity($folder, $form)) {
                $this->addNewTagsFromText($folder, (string) $form->get('newTags')->getData(), $tagRepository, $entityManager);
                $entityManager->persist($folder);
                $entityManager->flush();

                return $this->redirectToRoute('app_document_admin_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/document_library/folder_new.html.twig', [
            'folder' => $folder,
            'form' => $form,
            'rootRoleSelectMode' => true,
        ]);
    }

    #[Route('/folder/{id}/edit', name: 'app_document_admin_folder_edit', methods: ['GET', 'POST'])]
    public function editFolder(
        Request $request,
        DocumentFolder $folder,
        DocumentFolderRepository $folderRepository,
        DocumentTagRepository $tagRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(DocumentFolderType::class, $folder, [
            'parent_choices' => $folderRepository->findPotentialParents($folder),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->validateFolderSecurity($folder, $form)) {
                $this->addNewTagsFromText($folder, (string) $form->get('newTags')->getData(), $tagRepository, $entityManager);
                $entityManager->flush();

                return $this->redirectToRoute('app_document_admin_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/document_library/folder_edit.html.twig', [
            'folder' => $folder,
            'form' => $form,
            'rootRoleSelectMode' => false,
        ]);
    }

    #[Route('/folder/{id}', name: 'app_document_admin_folder_delete', methods: ['POST'])]
    public function deleteFolder(
        Request $request,
        DocumentFolder $folder,
        DocumentFolderRepository $folderRepository,
        EntityManagerInterface $entityManager,
        DocumentStorageService $documentStorageService
    ): Response {
        $returnFolder = null;
        $returnFolderId = (int) $request->request->get('return_folder', 0);
        if ($returnFolderId > 0) {
            $candidate = $folderRepository->find($returnFolderId);
            if ($candidate instanceof DocumentFolder) {
                $returnFolder = $candidate;
            }
        }

        if ($this->isCsrfTokenValid('delete_folder'.$folder->getId(), $request->getPayload()->getString('_token'))) {
            foreach ($this->collectFolderItems($folder) as $item) {
                $documentStorageService->removeStoredFile($item->getStoredFilename());
            }

            $entityManager->remove($folder);
            $entityManager->flush();
        }

        return $this->redirectToFolder($returnFolder);
    }

    #[Route('/folder/{id}/item/new', name: 'app_document_admin_item_new', methods: ['GET', 'POST'])]
    public function newItem(
        Request $request,
        DocumentFolder $folder,
        EntityManagerInterface $entityManager,
        DocumentStorageService $documentStorageService
    ): Response {
        $item = new DocumentItem();
        $item->setFolder($folder);
        $form = $this->createForm(DocumentItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->hydrateItemFromForm($item, $form, $documentStorageService)) {
                $entityManager->persist($item);
                $entityManager->flush();

                return $this->redirectToRoute('app_document_admin_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/document_library/item_new.html.twig', [
            'folder' => $folder,
            'item' => $item,
            'form' => $form,
        ]);
    }

    #[Route('/item/{id}/edit', name: 'app_document_admin_item_edit', methods: ['GET', 'POST'])]
    public function editItem(
        Request $request,
        DocumentItem $item,
        EntityManagerInterface $entityManager,
        DocumentStorageService $documentStorageService
    ): Response {
        $form = $this->createForm(DocumentItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->hydrateItemFromForm($item, $form, $documentStorageService)) {
                $entityManager->flush();

                return $this->redirectToRoute('app_document_admin_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/document_library/item_edit.html.twig', [
            'item' => $item,
            'form' => $form,
        ]);
    }

    #[Route('/item/{id}', name: 'app_document_admin_item_delete', methods: ['POST'])]
    public function deleteItem(
        Request $request,
        DocumentItem $item,
        DocumentFolderRepository $folderRepository,
        EntityManagerInterface $entityManager,
        DocumentStorageService $documentStorageService
    ): Response {
        $returnFolder = null;
        $returnFolderId = (int) $request->request->get('return_folder', 0);
        if ($returnFolderId > 0) {
            $candidate = $folderRepository->find($returnFolderId);
            if ($candidate instanceof DocumentFolder) {
                $returnFolder = $candidate;
            }
        }

        if ($this->isCsrfTokenValid('delete_item'.$item->getId(), $request->getPayload()->getString('_token'))) {
            $documentStorageService->removeStoredFile($item->getStoredFilename());
            $entityManager->remove($item);
            $entityManager->flush();
        }

        if (null === $returnFolder && $item->getFolder() instanceof DocumentFolder) {
            $returnFolder = $item->getFolder();
        }

        return $this->redirectToFolder($returnFolder);
    }

    private function validateFolderSecurity(DocumentFolder $folder, FormInterface $form): bool
    {
        if ($folder->getParent() instanceof DocumentFolder) {
            $folder->setIsSecured(false);
            $folder->setAccessRoles([]);

            return true;
        }

        if (!$form->has('isSecured')) {
            $selectedRoles = $folder->getAccessRoles();
            if ($selectedRoles === []) {
                $folder->setIsSecured(false);

                return true;
            }

            $folder->setAccessRoles([$selectedRoles[0]]);
            $folder->setIsSecured(true);

            return true;
        }

        if (!$folder->isSecured()) {
            $folder->setAccessRoles([]);

            return true;
        }

        if ($folder->getAccessRoles() === []) {
            $form->get('accessRoles')->addError(new FormError('Selectionnez au moins un role pour un dossier securise.'));
            return false;
        }

        return true;
    }

    private function hydrateItemFromForm(DocumentItem $item, FormInterface $form, DocumentStorageService $documentStorageService): bool
    {
        $uploadedFile = $form->get('uploadedFile')->getData();
        if (!$uploadedFile instanceof UploadedFile && null !== $uploadedFile) {
            $uploadedFile = null;
        }

        $itemType = $item->getItemType();

        if ($itemType === DocumentItem::TYPE_UPLOADED_FILE) {
            if (!$uploadedFile instanceof UploadedFile && $item->getStoredFilename() === null) {
                $form->get('uploadedFile')->addError(new FormError('Veuillez importer un fichier.'));
                return false;
            }

            if ($uploadedFile instanceof UploadedFile) {
                $documentStorageService->removeStoredFile($item->getStoredFilename());
                $storedFile = $documentStorageService->storeUploadedFile($uploadedFile);
                $item->setStoredFilename($storedFile['storedFilename']);
                $item->setOriginalFilename($storedFile['originalFilename']);
                $item->setMimeType($storedFile['mimeType']);
                $item->setFileSize($storedFile['fileSize']);
            }

            $item->setExternalUrl(null);

            return true;
        }

        $url = trim((string) $item->getExternalUrl());
        if ($url === '') {
            $form->get('externalUrl')->addError(new FormError('Veuillez saisir un lien.'));
            return false;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $form->get('externalUrl')->addError(new FormError('Le lien saisi est invalide.'));
            return false;
        }

        if ($itemType === DocumentItem::TYPE_VIDEO_LINK && !$this->isYoutubeOrVimeoUrl($url)) {
            $form->get('externalUrl')->addError(new FormError('Le lien video doit provenir de YouTube ou Vimeo.'));
            return false;
        }

        $documentStorageService->removeStoredFile($item->getStoredFilename());
        $item->setStoredFilename(null);
        $item->setOriginalFilename(null);
        $item->setMimeType(null);
        $item->setFileSize(null);
        $item->setExternalUrl($url);

        return true;
    }

    private function isYoutubeOrVimeoUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        return str_contains($host, 'youtube.com')
            || str_contains($host, 'youtu.be')
            || str_contains($host, 'vimeo.com');
    }

    private function handleCreateFolder(
        Request $request,
        DocumentFolderRepository $folderRepository,
        DocumentTagRepository $tagRepository,
        EntityManagerInterface $entityManager,
        ?DocumentFolder $currentFolder
    ): Response {
        $name = trim((string) $request->request->get('name', ''));
        if ($name === '') {
            $this->addFlash('error', 'Le nom du dossier est obligatoire.');

            return $this->redirectToFolder($currentFolder);
        }

        $position = max(0, (int) $request->request->get('position', 0));

        $parent = null;
        $parentId = (int) $request->request->get('parent_id', 0);
        if ($parentId > 0) {
            $parent = $folderRepository->find($parentId);
            if (!$parent instanceof DocumentFolder) {
                $this->addFlash('error', 'Le dossier parent est introuvable.');

                return $this->redirectToFolder($currentFolder);
            }
        }

        $folder = new DocumentFolder();
        $folder->setName($name);
        $folder->setDescription(null);
        $folder->setPosition($position);
        $folder->setParent($parent);

        if ($parent instanceof DocumentFolder) {
            $folder->setIsSecured(false);
            $folder->setAccessRoles([]);
        } else {
            $selectedRole = strtoupper(trim((string) $request->request->get('access_role', '')));
            if ($selectedRole !== '' || $request->request->has('access_role')) {
                $folder->setAccessRoles($selectedRole === '' ? [] : [$selectedRole]);
                $folder->setIsSecured($selectedRole !== '');
            } else {
                $isSecured = $request->request->getBoolean('is_secured', false);
                $accessRoles = $request->request->all('access_roles');
                $accessRoles = is_array($accessRoles) ? $accessRoles : [];
                $folder->setIsSecured($isSecured);
                $folder->setAccessRoles($accessRoles);

                if ($isSecured && $folder->getAccessRoles() === []) {
                    $this->addFlash('error', 'Selectionnez au moins un role pour un dossier racine securise.');

                    return $this->redirectToFolder($currentFolder);
                }
            }
        }

        $this->syncFolderTagsFromRequest($folder, $request, $tagRepository, $entityManager);

        $entityManager->persist($folder);
        $entityManager->flush();
        $this->addFlash('success', 'Dossier cree avec succes.');

        return $this->redirectToFolder($parent);
    }

    private function handleCreateItem(
        Request $request,
        DocumentFolderRepository $folderRepository,
        EntityManagerInterface $entityManager,
        DocumentStorageService $documentStorageService,
        ?DocumentFolder $currentFolder
    ): Response {
        $folderId = (int) $request->request->get('folder_id', 0);
        $folder = $folderId > 0 ? $folderRepository->find($folderId) : null;
        if (!$folder instanceof DocumentFolder) {
            $this->addFlash('error', 'Selectionnez un dossier valide avant d ajouter un fichier.');

            return $this->redirectToFolder($currentFolder);
        }

        $title = trim((string) $request->request->get('title', ''));
        if ($title === '') {
            $this->addFlash('error', 'Le titre du fichier est obligatoire.');

            return $this->redirectToFolder($folder);
        }

        $itemType = trim((string) $request->request->get('item_type', DocumentItem::TYPE_EXTERNAL_LINK));
        $allowedTypes = [
            DocumentItem::TYPE_VIDEO_LINK,
            DocumentItem::TYPE_EXTERNAL_LINK,
            DocumentItem::TYPE_UPLOADED_FILE,
        ];
        if (!in_array($itemType, $allowedTypes, true)) {
            $itemType = DocumentItem::TYPE_EXTERNAL_LINK;
        }

        $item = new DocumentItem();
        $item->setFolder($folder);
        $item->setTitle($title);
        $item->setDescription(null);
        $item->setPosition(max(0, (int) $request->request->get('position', 0)));
        $item->setItemType($itemType);

        if ($itemType === DocumentItem::TYPE_UPLOADED_FILE) {
            $uploadedFile = $request->files->get('uploaded_file');
            if (!$uploadedFile instanceof UploadedFile) {
                $this->addFlash('error', 'Veuillez importer un fichier.');

                return $this->redirectToFolder($folder);
            }

            $stored = $documentStorageService->storeUploadedFile($uploadedFile);
            $item->setStoredFilename($stored['storedFilename']);
            $item->setOriginalFilename($stored['originalFilename']);
            $item->setMimeType($stored['mimeType']);
            $item->setFileSize($stored['fileSize']);
            $item->setExternalUrl(null);
        } else {
            $url = trim((string) $request->request->get('external_url', ''));
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                $this->addFlash('error', 'Veuillez saisir un lien valide.');

                return $this->redirectToFolder($folder);
            }

            if ($itemType === DocumentItem::TYPE_VIDEO_LINK && !$this->isYoutubeOrVimeoUrl($url)) {
                $this->addFlash('error', 'Le lien video doit provenir de YouTube ou Vimeo.');

                return $this->redirectToFolder($folder);
            }

            $item->setExternalUrl($url);
            $item->setStoredFilename(null);
            $item->setOriginalFilename(null);
            $item->setMimeType(null);
            $item->setFileSize(null);
        }

        $entityManager->persist($item);
        $entityManager->flush();
        $this->addFlash('success', 'Fichier ajoute avec succes.');

        return $this->redirectToFolder($folder);
    }

    private function redirectToFolder(?DocumentFolder $folder): Response
    {
        if ($folder instanceof DocumentFolder) {
            return $this->redirectToRoute('app_document_admin_index', ['folder' => $folder->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_document_admin_index', [], Response::HTTP_SEE_OTHER);
    }

    private function syncFolderTagsFromRequest(
        DocumentFolder $folder,
        Request $request,
        DocumentTagRepository $tagRepository,
        EntityManagerInterface $entityManager
    ): void {
        $tagValues = $request->request->all('tags');
        $tagValues = is_array($tagValues) ? $tagValues : [];
        $newTagNames = [];

        foreach ($tagValues as $tagValue) {
            $tagValue = trim((string) $tagValue);
            if ($tagValue === '') {
                continue;
            }

            if (str_starts_with($tagValue, 'new:')) {
                $newTagName = trim(substr($tagValue, 4));
                if ($newTagName !== '') {
                    $newTagNames[] = $newTagName;
                }

                continue;
            }

            if (ctype_digit($tagValue)) {
                $tag = $tagRepository->find((int) $tagValue);
                if ($tag instanceof DocumentTag) {
                    $folder->addTag($tag);
                    continue;
                }
            }

            $newTagNames[] = $tagValue;
        }

        $this->addNewTagsFromText($folder, implode(',', $newTagNames), $tagRepository, $entityManager);
    }

    private function addNewTagsFromText(
        DocumentFolder $folder,
        string $rawTags,
        DocumentTagRepository $tagRepository,
        EntityManagerInterface $entityManager
    ): void {
        $names = preg_split('/[,;\n]+/', $rawTags) ?: [];
        $createdTagsBySlug = [];

        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $slug = DocumentTag::slugify($name);
            if ($slug === '') {
                continue;
            }

            $tag = $createdTagsBySlug[$slug] ?? $tagRepository->findOneBySlug($slug);
            if (!$tag instanceof DocumentTag) {
                $tag = (new DocumentTag())->setName($name);
                $entityManager->persist($tag);
                $createdTagsBySlug[$slug] = $tag;
            }

            $folder->addTag($tag);
        }
    }

    /**
     * @return DocumentFolder[]
     */
    private function buildBreadcrumbs(?DocumentFolder $folder): array
    {
        if (!$folder instanceof DocumentFolder) {
            return [];
        }

        $breadcrumbs = [];
        $cursor = $folder;
        while ($cursor instanceof DocumentFolder) {
            $breadcrumbs[] = $cursor;
            $cursor = $cursor->getParent();
        }

        return array_reverse($breadcrumbs);
    }

    /**
     * @return iterable<DocumentItem>
     */
    private function collectFolderItems(DocumentFolder $folder): iterable
    {
        foreach ($folder->getItems() as $item) {
            yield $item;
        }

        foreach ($folder->getChildren() as $child) {
            yield from $this->collectFolderItems($child);
        }
    }
}
