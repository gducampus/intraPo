<?php

namespace App\Service;

use App\Entity\ApplicationFeature;
use App\Repository\ApplicationFeatureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

final class FeatureCatalogSynchronizer
{
    private const EXCLUDED_ROUTE_NAMES = [
        'app_home',
        'app_logout',
        'app_login_email',
        'app_login_code',
        'app_verify_code',
        'app_module_page',
        'app_document_file_download',
        'app_feature_index',
        'app_feature_new',
        'app_feature_edit',
        'app_feature_delete',
        'app_feature_sync',
    ];

    public function __construct(
        private readonly RouterInterface $router,
        private readonly ApplicationFeatureRepository $featureRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function sync(): int
    {
        $existingByRoute = [];
        foreach ($this->featureRepository->findAll() as $feature) {
            if ($feature->getRouteName()) {
                $existingByRoute[$feature->getRouteName()] = true;
            }
        }

        $created = 0;
        foreach ($this->router->getRouteCollection()->all() as $routeName => $route) {
            if (!$this->isEligibleRoute($routeName, $route)) {
                continue;
            }

            if (isset($existingByRoute[$routeName])) {
                continue;
            }

            $feature = new ApplicationFeature();
            $feature->setLabel($this->buildLabel($routeName));
            $feature->setDescription('Fonctionnalite synchronisee automatiquement depuis les routes Symfony.');
            $feature->setRouteName($routeName);
            $feature->setCategory($this->guessCategory($route));
            $feature->setIcon($this->guessIcon($routeName, $route));

            $this->entityManager->persist($feature);
            $existingByRoute[$routeName] = true;
            ++$created;
        }

        if ($created > 0) {
            $this->entityManager->flush();
        }

        return $created;
    }

    private function isEligibleRoute(string $routeName, Route $route): bool
    {
        if (!str_starts_with($routeName, 'app_')) {
            return false;
        }

        if (in_array($routeName, self::EXCLUDED_ROUTE_NAMES, true)) {
            return false;
        }

        $path = $route->getPath();
        if ($path === '' || str_starts_with($path, '/_')) {
            return false;
        }

        $methods = $route->getMethods();
        if (!empty($methods) && !in_array('GET', $methods, true)) {
            return false;
        }

        return true;
    }

    private function buildLabel(string $routeName): string
    {
        $name = preg_replace('/^app_/', '', $routeName) ?? $routeName;
        $name = str_replace(['_index', '_new', '_edit', '_show'], [' list', ' new', ' edit', ' show'], $name);
        $name = str_replace('_', ' ', $name);
        $name = trim($name);

        return ucwords($name);
    }

    private function guessCategory(Route $route): string
    {
        $path = $route->getPath();

        if (str_starts_with($path, '/admin')) {
            return 'Administration';
        }

        if (str_starts_with($path, '/module')) {
            return 'Modules';
        }

        return 'Application';
    }

    private function guessIcon(string $routeName, Route $route): string
    {
        $context = strtolower($routeName.' '.$route->getPath());

        return match (true) {
            str_contains($context, 'user') => 'users',
            str_contains($context, 'secteur') => 'building-2',
            str_contains($context, 'module') => 'layout-grid',
            str_contains($context, 'feature') => 'sparkles',
            str_contains($context, 'document') || str_contains($context, 'folder') => 'folders',
            str_contains($context, 'home') || $route->getPath() === '/' => 'house',
            default => 'square',
        };
    }
}
