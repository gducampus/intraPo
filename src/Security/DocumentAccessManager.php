<?php

namespace App\Security;

use App\Entity\DocumentFolder;
use Symfony\Component\Security\Core\User\UserInterface;

final class DocumentAccessManager
{
    public function canAccessFolder(?UserInterface $user, DocumentFolder $folder): bool
    {
        if (!$user instanceof UserInterface) {
            return false;
        }

        $roles = array_values(array_unique(array_filter(
            array_map('strtoupper', $user->getRoles()),
            static fn (string $role): bool => $role !== ''
        )));

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return true;
        }

        $cursor = $folder;
        while ($cursor instanceof DocumentFolder) {
            if ($cursor->isSecured()) {
                $allowedRoles = array_values(array_unique(array_filter(
                    array_map('strtoupper', $cursor->getAccessRoles()),
                    static fn (string $role): bool => $role !== ''
                )));

                if ($allowedRoles === []) {
                    return false;
                }

                $hasAllowedRole = false;
                foreach ($allowedRoles as $allowedRole) {
                    if (in_array($allowedRole, $roles, true)) {
                        $hasAllowedRole = true;
                        break;
                    }
                }

                if (!$hasAllowedRole) {
                    return false;
                }
            }

            $cursor = $cursor->getParent();
        }

        return true;
    }
}
