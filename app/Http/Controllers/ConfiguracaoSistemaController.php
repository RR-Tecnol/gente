<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracaoSistema;
use App\MyLibs\PerfilEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConfiguracaoSistemaController extends Controller
{
    /**
     * GET /configuracoes
     * Retorna todas as configurações + view.
     */
    public function index()
    {
        $this->autorizarAdmin();
        $configuracoes = ConfiguracaoSistema::listar();
        return view('configuracao.index', compact('configuracoes'));
    }

    /**
     * GET /configuracoes/api
     * Retorna as configurações como JSON para o componente Vue.
     */
    public function api()
    {
        return response()->json(
            ConfiguracaoSistema::listar()->keyBy('CONFIG_CHAVE')
        );
    }

    /**
     * PUT /configuracoes/{chave}
     * Atualiza o valor de uma configuração.
     */
    public function update(Request $request, string $chave)
    {
        $this->autorizarAdmin();

        $config = ConfiguracaoSistema::where('CONFIG_CHAVE', $chave)->firstOrFail();

        $valor = $request->input('CONFIG_VALOR');

        // Validação por tipo
        if ($config->CONFIG_TIPO === 'BOOLEAN') {
            $valor = in_array($valor, ['1', 1, true, 'true'], true) ? '1' : '0';
        } elseif ($config->CONFIG_TIPO === 'NUMBER') {
            $valor = (string) max(0, (float) $valor);
        }

        ConfiguracaoSistema::set($chave, $valor, Auth::id());

        return response()->json([
            'retorno' => ['CONFIG_CHAVE' => $chave, 'CONFIG_VALOR' => $valor],
            'mensagem' => 'Configuração atualizada com sucesso.',
        ]);
    }

    // ──────────────────────────────────────────────
    //  Privado
    // ──────────────────────────────────────────────

    private function autorizarAdmin(): void
    {
        $usuario = Auth::user();
        $perfilId = optional($usuario->perfil)->PERFIL_ID ?? null;

        $permitidos = [PerfilEnum::DESENVOLVEDOR, PerfilEnum::ADMINISTRADOR];

        if (!in_array($perfilId, $permitidos)) {
            abort(403, 'Acesso restrito a administradores.');
        }
    }
}
