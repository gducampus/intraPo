<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class DocumentStorageService
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir
    ) {
    }

    /**
     * @return array{storedFilename: string, originalFilename: string, mimeType: ?string, fileSize: ?int}
     */
    public function storeUploadedFile(UploadedFile $uploadedFile): array
    {
        $this->ensureStorageDirectory();

        $extension = strtolower((string) $uploadedFile->guessExtension());
        if ($extension === '') {
            $extension = strtolower((string) pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION));
        }

        $storedFilename = bin2hex(random_bytes(16));
        if ($extension !== '') {
            $storedFilename .= '.'.$extension;
        }

        $uploadedFile->move($this->getStorageDirectory(), $storedFilename);

        return [
            'storedFilename' => $storedFilename,
            'originalFilename' => $uploadedFile->getClientOriginalName(),
            'mimeType' => $uploadedFile->getMimeType() ?: $uploadedFile->getClientMimeType(),
            'fileSize' => $uploadedFile->getSize(),
        ];
    }

    public function getAbsolutePath(?string $storedFilename): ?string
    {
        $storedFilename = trim((string) $storedFilename);
        if ($storedFilename === '') {
            return null;
        }

        return $this->getStorageDirectory().DIRECTORY_SEPARATOR.$storedFilename;
    }

    public function removeStoredFile(?string $storedFilename): void
    {
        $path = $this->getAbsolutePath($storedFilename);
        if (null === $path || !is_file($path)) {
            return;
        }

        @unlink($path);
    }

    private function getStorageDirectory(): string
    {
        return $this->projectDir.DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'document-library';
    }

    private function ensureStorageDirectory(): void
    {
        $storageDirectory = $this->getStorageDirectory();

        if (!is_dir($storageDirectory)) {
            mkdir($storageDirectory, 0775, true);
        }
    }
}
