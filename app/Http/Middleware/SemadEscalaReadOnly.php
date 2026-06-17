<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use App\Support\RbacResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Bloqueia POST/PUT/PATCH/DELETE em rotas de escala de trabalho para utilizadores
 * com assignment ao papel auditoria_matriz_semad (somente leitura).
 *
 * Nota (Fase 3C): a regra SEMAD para mutações está também em
 * {@see \App\Support\TenantScope\TenantScopeEvaluator} quando o anel tem
 * `semad_block_mutations`. Manter este middleware até `GENTE_TENANT_SCOPE_ENFORCE`
 * estar estável; depois pode retirar-se gradualmente em favor da Manta única.
 */
class SemadEscalaReadOnly
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }
        if (strpos((string) $request->path(), 'escala-trabalho') === false) {
            return $next($request);
        }
        $user = Auth::user();
        if (! $user instanceof Usuario) {
            return $next($request);
        }
        $resolver = new RbacResolver();
        if ($resolver->usuarioTemPapelSemadAuditoria((int) $user->getAttribute('USUARIO_ID'))) {
            return response()->json([
                'ok' => false,
                'erro' => 'Perfil SEMAD (auditoria matriz): operações de escrita na escala de trabalho não são permitidas.',
            ], 403);
        }

        return $next($request);
    }
}
