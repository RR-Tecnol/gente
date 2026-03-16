<?php

namespace App\Http\Middleware;

use App\Models\ConfiguracaoSistema;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware que bloqueia acesso a módulos desabilitados via CONFIGURACAO_SISTEMA.
 *
 * Uso nas rotas:
 *   Route::middleware('modulo.ativo:MODULO_PONTO_ATIVO')->group(...)
 */
class CheckModuloAtivo
{
    public function handle(Request $request, Closure $next, string $chave)
    {
        if (!ConfiguracaoSistema::get($chave, false)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'erro' => 'Este módulo não está habilitado. Acesse Configurações para ativá-lo.'
                ], 403);
            }

            abort(403, 'Este módulo não está habilitado. Acesse Configurações para ativá-lo.');
        }

        return $next($request);
    }
}
