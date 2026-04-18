<?php

namespace App\Service;

use App\Entity\ApplicationFeature;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

final class ModuleFeatureLinkResolver
{
    public function __construct(private readonly RouterInterface $router)
    {
    }

    public function resolve(ApplicationFeature $feature): ?string
    {
        $url = trim((string) $feature->getUrl());
        if ($url !== '') {
            return $this->isAdminPath($url) ? null : $url;
        }

        $routeName = trim((string) $feature->getRouteName());
        if ($routeName === '') {
            return null;
        }

        $route = $this->router->getRouteCollection()->get($routeName);
        if ($route === null || $this->isAdminPath($route->getPath())) {
            return null;
        }

        $parameters = [];
        foreach ($route->compile()->getVariables() as $variable) {
            if (!$route->hasDefault($variable)) {
                return null;
            }

            $parameters[$variable] = $route->getDefault($variable);
        }

        try {
            return $this->router->generate($routeName, $parameters);
        } catch (Throwable) {
            return null;
        }
    }

    private function isAdminPath(string $path): bool
    {
        $parsedPath = parse_url($path, PHP_URL_PATH);
        if (!is_string($parsedPath) || $parsedPath === '') {
            $parsedPath = $path;
        }

        return str_starts_with($parsedPath, '/admin');
    }
}
