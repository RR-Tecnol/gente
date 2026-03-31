<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ConsignatariaController;
use App\Http\Controllers\LayoutConsignatariaController;
use App\Http\Controllers\ConsigRemessaController;

Route::prefix('consignatarias')->middleware(['perfil:ADMIN'])->group(function () {
    // CONSIGNATARIA LIST / CREATE (rotas estáticas sem wildcard)
    Route::get('/', [ConsignatariaController::class, 'index']);
    Route::post('/', [ConsignatariaController::class, 'store']);

    // REMESSAS — declarar ANTES dos wildcards /{id}
    Route::get('/remessas', [ConsigRemessaController::class, 'index']);
    Route::post('/remessas/gerar', [ConsigRemessaController::class, 'gerar']);
    Route::get('/remessas/{rid}/download', [ConsigRemessaController::class, 'download']);

    // CONSIGNATARIA CRUD (wildcards por último)
    Route::post('/{id}/importar', [ConsigRemessaController::class, 'importar'])
         ->middleware('upload.safe');
    Route::get('/{id}', [ConsignatariaController::class, 'show']);
    Route::put('/{id}', [ConsignatariaController::class, 'update']);
    Route::patch('/{id}/toggle-ativa', [ConsignatariaController::class, 'toggleAtiva']);

    // LAYOUTS (wildcards)
    Route::get('/{id}/layouts', [LayoutConsignatariaController::class, 'index']);
    Route::post('/{id}/layouts', [LayoutConsignatariaController::class, 'store']);
    Route::put('/{id}/layouts/{lid}', [LayoutConsignatariaController::class, 'update']);
});
