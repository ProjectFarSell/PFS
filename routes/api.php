<?php

use App\Http\Controllers\Api\V1\CatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn (): array => [
        'name' => config('app.name'),
        'status' => 'ok',
        'api_version' => 'v1',
    ]);

    Route::get('/catalog', [CatalogController::class, 'index']);
});
