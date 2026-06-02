<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/storage/{path}', function (string $path) {
    $resolvePublicFile = function (string $root, string $relativePath): ?string {
        $realRoot = realpath($root);
        $filePath = realpath($root.'/'.$relativePath);

        if (
            ! $realRoot ||
            ! $filePath ||
            ! str_starts_with($filePath, $realRoot.DIRECTORY_SEPARATOR) ||
            ! is_file($filePath)
        ) {
            return null;
        }

        return $filePath;
    };

    $filePath = $resolvePublicFile(storage_path('app/public'), $path)
        ?? $resolvePublicFile(public_path(), $path);

    abort_unless($filePath, 404);

    return response()->file($filePath);
})->where('path', '.*');

Route::get('/clear-cache', function() {
    Artisan::call('optimize:clear');
    return 'Cache aman bre!';
});
