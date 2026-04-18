<?php

namespace App\Service;

use App\Entity\Module;
use App\Entity\ModuleFeature;
use App\Repository\ApplicationFeatureRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ModuleEditorHelper
{
    /**
     * @var array<string, string>
     */
    private const ENTITY_TOKEN_LABELS = [
        'po_user' => 'Utilisateur',
        'user' => 'Utilisateur',
        'users' => 'Utilisateur',
        'member' => 'Membre',
        'members' => 'Membre',
        'secteur' => 'Secteur',
        'sector' => 'Secteur',
        'sectors' => 'Secteur',
        'role' => 'Role',
        'roles' => 'Role',
        'module' => 'Module',
        'modules' => 'Module',
        'feature' => 'Fonctionnalite',
        'features' => 'Fonctionnalite',
        'document' => 'Document',
        'documents' => 'Document',
        'folder' => 'Dossier',
        'folders' => 'Dossier',
        'login' => 'Connexion',
        'history' => 'Historique',
    ];

    /**
     * @var string[]
     */
    private const NON_ENTITY_TOKENS = [
        'app',
        'admin',
        'index',
        'new',
        'edit',
        'show',
        'delete',
        'remove',
        'assign',
        'sync',
        'list',
        'create',
        'update',
        'view',
        'map',
        'email',
        'code',
        'all',
        'by',
        'from',
        'to',
    ];

    /**
     * @var string[]
     */
    private const GENERIC_CATEGORY_NAMES = [
        'administration',
        'application',
        'modules',
    ];

    /**
     * @return array{featureCatalogGroups: array<int, array{id: string, name: string, features: array<int, array{id: int, label: string, description: string}>, featureIds: int[]}>, selectedFeatureIds: int[], featureCatalogTotal: int}
     */
    public function buildFeatureCatalogData(
        Module $module,
        ApplicationFeatureRepository $featureRepository,
        ?array $submittedFeatureIds = null
    ): array {
        $groups = [];
        $groupIndex = 1;

        foreach ($featureRepository->findCatalogRows() as $featureRow) {
            $featureId = (int) ($featureRow['id'] ?? 0);
            if ($featureId <= 0) {
                continue;
            }

            $groupName = $this->resolveFeatureGroupName($featureRow);

            if (!isset($groups[$groupName])) {
                $groups[$groupName] = [
                    'id' => sprintf('feature-group-%d', $groupIndex),
                    'name' => $groupName,
                    'features' => [],
                    'featureIds' => [],
                ];
                ++$groupIndex;
            }

            $groups[$groupName]['features'][] = [
                'id' => $featureId,
                'label' => trim((string) ($featureRow['label'] ?? '')),
                'description' => trim((string) ($featureRow['description'] ?? '')),
            ];
            $groups[$groupName]['featureIds'][] = $featureId;
        }

        foreach ($groups as &$group) {
            usort(
                $group['features'],
                static fn (array $left, array $right): int => strnatcasecmp(
                    (string) ($left['label'] ?? ''),
                    (string) ($right['label'] ?? '')
                )
            );
        }
        unset($group);

        uasort(
            $groups,
            static fn (array $left, array $right): int => strnatcasecmp(
                (string) ($left['name'] ?? ''),
                (string) ($right['name'] ?? '')
            )
        );

        if (is_array($submittedFeatureIds)) {
            $selectedFeatureIds = $this->sanitizeFeatureIds($submittedFeatureIds);
        } else {
            $selectedFeatureIds = [];
            foreach ($module->getModuleFeatures() as $moduleFeature) {
                $featureId = $moduleFeature->getFeature()?->getId();
                if ($featureId !== null) {
                    $selectedFeatureIds[] = $featureId;
                }
            }

            $selectedFeatureIds = array_values(array_unique($selectedFeatureIds));
        }

        return [
            'featureCatalogGroups' => array_values($groups),
            'selectedFeatureIds' => $selectedFeatureIds,
            'featureCatalogTotal' => array_sum(
                array_map(
                    static fn (array $group): int => count($group['features'] ?? []),
                    $groups
                )
            ),
        ];
    }

    /**
     * @param mixed[] $rawValues
     * @return int[]
     */
    public function sanitizeFeatureIds(array $rawValues): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, $rawValues),
            static fn (int $value): bool => $value > 0
        )));
    }

    /**
     * @param int[] $submittedFeatureIds
     */
    public function syncModuleFeatures(
        Module $module,
        array $submittedFeatureIds,
        ApplicationFeatureRepository $featureRepository,
        EntityManagerInterface $entityManager
    ): void {
        $existingByFeatureId = [];
        $duplicates = [];

        foreach ($module->getModuleFeatures() as $moduleFeature) {
            $featureId = $moduleFeature->getFeature()?->getId();
            if ($featureId === null) {
                $duplicates[] = $moduleFeature;
                continue;
            }

            if (isset($existingByFeatureId[$featureId])) {
                $duplicates[] = $moduleFeature;
                continue;
            }

            $existingByFeatureId[$featureId] = $moduleFeature;
        }

        foreach ($duplicates as $duplicate) {
            $entityManager->remove($duplicate);
        }

        $submittedLookup = array_fill_keys($submittedFeatureIds, true);
        foreach ($existingByFeatureId as $featureId => $moduleFeature) {
            if (!isset($submittedLookup[$featureId])) {
                $entityManager->remove($moduleFeature);
                unset($existingByFeatureId[$featureId]);
            }
        }

        if ($submittedFeatureIds === []) {
            return;
        }

        $catalogFeatures = $featureRepository->findBy(['id' => $submittedFeatureIds]);
        $catalogById = [];
        foreach ($catalogFeatures as $catalogFeature) {
            $catalogById[$catalogFeature->getId()] = $catalogFeature;
        }

        $position = 0;
        foreach ($submittedFeatureIds as $featureId) {
            if (!isset($catalogById[$featureId])) {
                continue;
            }

            $moduleFeature = $existingByFeatureId[$featureId] ?? null;
            if (!$moduleFeature instanceof ModuleFeature) {
                $moduleFeature = new ModuleFeature();
                $moduleFeature->setModule($module);
                $moduleFeature->setFeature($catalogById[$featureId]);
                $entityManager->persist($moduleFeature);
            }

            $moduleFeature->setPosition($position);
            ++$position;
        }
    }

    /**
     * @param array{id?: mixed, label?: mixed, description?: mixed, category?: mixed, routeName?: mixed, url?: mixed} $featureRow
     */
    private function resolveFeatureGroupName(array $featureRow): string
    {
        $sourceValues = [
            (string) ($featureRow['routeName'] ?? ''),
            (string) ($featureRow['url'] ?? ''),
            (string) ($featureRow['label'] ?? ''),
            (string) ($featureRow['description'] ?? ''),
        ];

        foreach ($sourceValues as $sourceValue) {
            $token = $this->extractEntityToken($this->tokenize($sourceValue));
            if ($token !== null) {
                return $this->humanizeEntityToken($token);
            }
        }

        $category = trim((string) ($featureRow['category'] ?? ''));
        if (
            $category !== ''
            && !in_array(strtolower($category), self::GENERIC_CATEGORY_NAMES, true)
        ) {
            return $category;
        }

        return 'Autres';
    }

    /**
     * @param string[] $tokens
     */
    private function extractEntityToken(array $tokens): ?string
    {
        $tokens = array_values(array_filter(
            $tokens,
            static fn (string $token): bool => $token !== ''
        ));

        $tokenCount = count($tokens);
        if ($tokenCount < 1) {
            return null;
        }

        for ($index = 0; $index < $tokenCount - 1; ++$index) {
            $compound = $tokens[$index].'_'.$tokens[$index + 1];
            if (isset(self::ENTITY_TOKEN_LABELS[$compound])) {
                return $compound;
            }
        }

        foreach ($tokens as $token) {
            if (in_array($token, self::NON_ENTITY_TOKENS, true)) {
                continue;
            }

            if (isset(self::ENTITY_TOKEN_LABELS[$token])) {
                return $token;
            }

            if (preg_match('/^[a-z][a-z0-9_]{2,}$/', $token) === 1) {
                return $token;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function tokenize(string $source): array
    {
        $normalized = strtolower(trim($source));
        if ($normalized === '') {
            return [];
        }

        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');
        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(
            explode('_', $normalized),
            static fn (string $token): bool => $token !== ''
        ));
    }

    private function humanizeEntityToken(string $token): string
    {
        if (isset(self::ENTITY_TOKEN_LABELS[$token])) {
            return self::ENTITY_TOKEN_LABELS[$token];
        }

        $normalized = str_replace('_', ' ', $token);
        if (str_ends_with($normalized, 's') && strlen($normalized) > 4) {
            $normalized = substr($normalized, 0, -1);
        }

        return ucfirst($normalized);
    }
}
