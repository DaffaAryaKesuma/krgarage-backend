<?php

namespace App\Http\Middleware;

use App\Support\RoleNormalizer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Menangani request yang masuk.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $pengguna = Auth::user();
        $rolePengguna = RoleNormalizer::normalizeOrNull($pengguna?->role);

        $roleDiizinkan = array_values(array_filter(array_map(
            static fn ($role) => RoleNormalizer::normalizeOrNull((string) $role),
            $roles,
        )));

        // Cek apakah pengguna sudah login DAN apakah rolenya ada di dalam daftar role yang diizinkan
        if (!Auth::check() || !$rolePengguna || !in_array($rolePengguna, $roleDiizinkan, true)) {
            // Jika tidak, tolak akses
            return response()->json(['message' => 'Anda tidak memiliki akses untuk melakukan tindakan ini.'], 403);
        }

        // Jika diizinkan, lanjutkan request
        return $next($request);
    }
}