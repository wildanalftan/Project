<?php

use Illuminate\Support\Facades\Route;

/**
 * SPA fallback route — serves index.html for all non-API paths.
 * React Router handles client-side routing after this.
 * The frontend dist is built into public/ via deploy.sh.
 */
Route::get('/{any}', function () {
    $indexPath = public_path('index.html');
    if (file_exists($indexPath)) {
        return response()->file($indexPath);
    }
    return response()->json(['message' => 'Frontend belum di-build. Jalankan npm run build di folder frontend.'], 404);
})->where('any', '.*');
