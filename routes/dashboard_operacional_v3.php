<?php

/**
 * Fase 9A — Painel executivo (KPIs agregados).
 * Herda prefix api/v3 + middleware do grupo em api_v3_auth_part1.php.
 */

use App\Http\Controllers\Api\V3\DashboardOperacionalController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard/operacional', DashboardOperacionalController::class)->name('api.v3.dashboard.operacional');
