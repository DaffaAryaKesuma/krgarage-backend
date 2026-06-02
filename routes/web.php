<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/storage/{path}', function (string $path) {
    $storageRoot = realpath(storage_path('app/public'));
    $filePath = realpath(storage_path('app/public/'.$path));

    abort_unless(
        $storageRoot &&
        $filePath &&
        str_starts_with($filePath, $storageRoot.DIRECTORY_SEPARATOR) &&
        is_file($filePath),
        404
    );

    return response()->file($filePath);
})->where('path', '.*');

Route::get('/clear-cache', function() {
    Artisan::call('optimize:clear');
    return 'Cache aman bre!';
});
