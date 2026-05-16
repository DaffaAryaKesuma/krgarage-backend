<?php

namespace App\Support;

class RoleNormalizer
{
    /**
     * @var array<string, string>
     */
    private const ROLE_ALIASES = [
        'admin'     => 'admin',
        'mekanik'   => 'mekanik',
        'pemilik'   => 'pemilik',
        'pelanggan' => 'pelanggan',
    ];

    /**
     * Normalisasi role ke canonical value.
     */
    public static function normalizeOrNull(?string $role): ?string
    {
        if (!$role) {
            return null;
        }

        $roleKey = strtolower(trim($role));

        return self::ROLE_ALIASES[$roleKey] ?? null;
    }

    /**
     * Normalisasi role dengan fallback jika role tidak dikenali.
     */
    public static function normalize(?string $role, string $fallback = 'pelanggan'): string
    {
        return self::normalizeOrNull($role) ?? $fallback;
    }
}
