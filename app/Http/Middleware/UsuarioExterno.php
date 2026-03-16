<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UsuarioExterno
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Recupera o usuário logado e suas relações
        $user = Usuario::with(['perfilExterno', 'pessoaVinculada'])->find(Auth::id());

        // dd($user->pessoaVinculada);

        // Se o usuário tem um perfil externo
        if ($user && $user->perfilExterno) {
            // Obtém o PESSOA_ID relacionado ao funcionário do usuário
            $id = $user->pessoaVinculada->PESSOA_ID;

            // Lista de rotas permitidas
            $allowedRoutes = [
                'usuario.alteracao_senha',
                'usuario.alterar_senha',
                'pessoa.get_pessoa',
                'pessoa.update',
                'pessoa.listar',
                'cidade.search',
                'documento.create',
                'documento.update',
                'documento.delete',
                'certidao.create',
                'certidao.update',
                'certidao.delete',
                'cartorio.search',
                'pessoa_conselho.create',
                'pessoa_conselho.update',
                'pessoa_conselho.delete',
                'conselho.search',
                'contato.create',
                'contato.update',
                'contato.delete',
                'banco.search',
                'pessoa_banco.create',
                'pessoa_banco.update',
                'pessoa_banco.delete',
                'ocupacao.search',
                'pessoa_ocupacao.create',
                'pessoa_ocupacao.update',
                'pessoa_ocupacao.delete',
                'dependente.create',
                'dependente.update',
                'dependente.delete',
                'unidade.search',
                'atribuicao.search',
                'funcionario.create',
                'funcionario.update',
                'comentario.list',
                'comentario.create',
                'download.termo',
                'inserir.termo_usuario',
                // Contra-cheque: servidor externo pode ver seu próprio holerite
                'contra-cheque',
                'meus_holerites.listar',
                'folha.search',
            ];

            // Verifica se a URL da requisição corresponde a uma das rotas permitidas através do nome da mesma
            foreach ($allowedRoutes as $route) {
                if ($request->routeIs($route)) {
                    return $next($request);
                }
            }

            // Verifica se a rota é 'cad_pessoa_view' e se o 'pessoaId' corresponde
            if ($request->routeIs('cad_pessoa_view')) {
                $pessoaIdFromRoute = intval($request->route('pessoaId'));
                if ($pessoaIdFromRoute == $id) {
                    return $next($request);
                }
            }

            // Redireciona para a rota correta se o usuário estiver tentando acessar outro 'pessoaId'
            return redirect()->route('cad_pessoa_view', ['pessoaId' => $id]);
        }

        // Continua com a requisição se o usuário não for externo ou a verificação falhar
        return $next($request);
    }
}
