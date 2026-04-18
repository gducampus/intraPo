<?php

namespace App\Security;

use App\Entity\Module;
use Symfony\Component\Security\Core\User\UserInterface;

final class ModuleAccessManager
{
    /**
     * @param iterable<Module> $modules
     * @return Module[]
     */
    public function filterAccessibleModules(?UserInterface $user, iterable $modules): array
    {
        $accessible = [];

        foreach ($modules as $module) {
            if ($this->canAccessModule($user, $module)) {
                $accessible[] = $module;
            }
        }

        return $accessible;
    }

    public function canAccessModule(?UserInterface $user, Module $module): bool
    {
        if (!$module->isAvailable() || !$user) {
            return false;
        }

        $roles = array_map('strtoupper', $user->getRoles());

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return true;
        }

        foreach ($this->expectedRoles($module) as $role) {
            if (in_array($role, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    public function expectedRoles(Module $module): array
    {
        $expected = [];
        $normalizedTag = $this->normalize($module->getTag() ?? '');

        if ($normalizedTag === '') {
            $normalizedTag = $this->normalize($module->getTitle() ?? '');
        }

        if ($normalizedTag !== '') {
            if ($normalizedTag === 'ADMIN') {
                $expected[] = 'ROLE_ADMIN';
            } else {
                $expected[] = 'ROLE_MODULE_ALL';
                $expected[] = sprintf('ROLE_MODULE_%s', $normalizedTag);
                $expected[] = sprintf('ROLE_%s', $normalizedTag);
            }
        }

        $expected = array_merge($expected, array_map('strtoupper', $module->getAccessRoles()));

        return array_values(array_unique(array_filter(
            $expected,
            static fn (string $role): bool => $role !== ''
        )));
    }

    private function normalize(string $value): string
    {
        $normalized = strtoupper(trim($value));
        $normalized = preg_replace('/[^A-Z0-9]+/', '_', $normalized) ?? '';

        return trim($normalized, '_');
    }
}
