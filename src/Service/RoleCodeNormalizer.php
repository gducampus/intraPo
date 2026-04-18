<?php

namespace App\Service;

final class RoleCodeNormalizer
{
    public function normalize(string $code): string
    {
        $normalized = strtoupper(trim($code));
        $normalized = preg_replace('/[^A-Z0-9]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        if ($normalized === '') {
            return '';
        }

        if (!str_starts_with($normalized, 'ROLE_')) {
            $normalized = 'ROLE_'.$normalized;
        }

        return $normalized;
    }
}

