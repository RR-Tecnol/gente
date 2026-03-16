<?php

namespace App\Http\Controllers;

use App\Models\JustificativaPonto;
use App\MyLibs\PerfilEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class JustificativaPontoController extends Controller
{
    /** GET /ponto/justificativas — Fila para o gestor */
    public function index(Request $request)
    {
        $usuario = Auth::user();
        $perfil = optional($usuario->perfil)->PERFIL_ID;

        // Gestor de equipe vê só do seu setor
        $setorId = null;
        if ($perfil === PerfilEnum::COORD_DE_SETOR) {
            $setorId = optional($usuario->funcionario)->SETOR_ID;
        }

        return response()->json([
            'retorno' => JustificativaPonto::pendentes($setorId),
        ]);
    }

    /** POST /ponto/justificativas — Funcionário abre justificativa */
    public function store(Request $request)
    {
        $request->validate([
            'apuracao_id' => 'required|integer',
            'justificativa_data' => 'required|date',
            'justificativa_motivo' => 'required|string|max:255',
        ]);

        $just = JustificativaPonto::create([
            'APURACAO_ID' => $request->apuracao_id,
            'JUSTIFICATIVA_DATA' => $request->justificativa_data,
            'JUSTIFICATIVA_MOTIVO' => $request->justificativa_motivo,
            'JUSTIFICATIVA_STATUS' => 'PENDENTE',
        ]);

        return response()->json(['retorno' => $just, 'mensagem' => 'Justificativa enviada para aprovação.'], 201);
    }

    /** POST /ponto/justificativas/{id}/aprovar */
    public function aprovar(Request $request, int $id)
    {
        $this->autorizarGestor();

        $just = JustificativaPonto::with('apuracao.funcionario')->findOrFail($id);
        $just->update([
            'JUSTIFICATIVA_STATUS' => 'APROVADA',
            'GESTOR_ID' => Auth::id(),
            'GESTOR_OBS' => $request->obs,
            'GESTOR_DECISAO_EM' => now(),
        ]);

        // Atualiza horas da apuração recalculando
        // @todo: descontar do APURACAO_HORAS_FALTA correspondente

        return response()->json(['retorno' => $just, 'mensagem' => 'Justificativa aprovada.']);
    }

    /** POST /ponto/justificativas/{id}/rejeitar */
    public function rejeitar(Request $request, int $id)
    {
        $this->autorizarGestor();

        $request->validate(['obs' => 'required|string|max:255']);

        $just = JustificativaPonto::findOrFail($id);
        $just->update([
            'JUSTIFICATIVA_STATUS' => 'REJEITADA',
            'GESTOR_ID' => Auth::id(),
            'GESTOR_OBS' => $request->obs,
            'GESTOR_DECISAO_EM' => now(),
        ]);

        return response()->json(['retorno' => $just, 'mensagem' => 'Justificativa rejeitada.']);
    }

    private function autorizarGestor(): void
    {
        $perfil = optional(Auth::user()->perfil)->PERFIL_ID;
        $ok = [
            PerfilEnum::DESENVOLVEDOR,
            PerfilEnum::ADMINISTRADOR,
            PerfilEnum::GESTAO,
            PerfilEnum::DIRETOR_GESTOR_UND,
            PerfilEnum::COORD_DE_SETOR,
        ];
        if (!in_array($perfil, $ok)) {
            abort(403, 'Sem permissão para avaliar justificativas.');
        }
    }
}
