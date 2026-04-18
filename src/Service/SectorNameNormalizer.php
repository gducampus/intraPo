<?php

namespace App\Service;

final class SectorNameNormalizer
{
    public function normalize(?string $name): string
    {
        if (null === $name) {
            return '';
        }

        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $ascii = false !== $ascii ? $ascii : $name;
        $ascii = mb_strtolower($ascii);
        $ascii = preg_replace('/[^a-z0-9]+/u', ' ', $ascii) ?? $ascii;
        $ascii = preg_replace('/\s+/u', ' ', $ascii) ?? $ascii;

        return trim($ascii);
    }
}

