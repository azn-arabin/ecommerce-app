<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/sync-user', [AuthController::class, 'apiSyncUser']);
Route::post('/sso-login', [AuthController::class, 'apiSSOLogin']);
Route::post('/sso-logout', function () {
    return response()->json(['success' => true]);
});
