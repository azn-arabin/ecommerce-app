<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// SSO API endpoints (called server-to-server by the partner app)
Route::post('/sso/validate', [AuthController::class, 'apiSsoValidate']);
Route::post('/sso/sync-user', [AuthController::class, 'apiSyncUser']);
