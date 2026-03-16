<?php

namespace App\Http\Controllers;

use App\Models\ApuracaoPonto;
use App\MyLibs\PerfilEnum;
use App\Services\ApuracaoPontoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApuracaoPontoController extends Controller
{
    public function __construct(private ApuracaoPontoService $service)
    {
    }

    /** GET /ponto/apuracao */
    public function index(Request $request)
    {
        return response()->json(['retorno' => ApuracaoPonto::pesquisar($request)]);
    }

    /** POST /ponto/apuracao/calcular */
    public function calcular(Request $request)
    {
        $request->validate([
            'funcionario_id' => 'required|integer',
            'apuracao_competencia' => 'required|string|size:7', // "YYYY-MM"
        ]);

        $apuracao = $this->service->calcular(
            $request->funcionario_id,
            $request->apuracao_competencia
        );

        return response()->json([
            'retorno' => $apuracao,
            'mensagem' => 'Apuração calculada.',
        ]);
    }

    /** POST /ponto/apuracao/{id}/fechar */
    public function fechar(int $id)
    {
        $this->autorizarGestorOuSuperintendente();

        $apuracao = $this->service->fechar($id);

        return response()->json([
            'retorno' => $apuracao,
            'mensagem' => 'Apuração fechada. Eventos de folha gerados.',
        ]);
    }

    /** GET /ponto/apuracao/{id}/espelho — PDF do espelho de ponto */
    public function espelho(int $id)
    {
        $apuracao = ApuracaoPonto::with([
            'funcionario.pessoa',
            'justificativas',
        ])->findOrFail($id);

        $registros = \App\Models\RegistroPonto::where('FUNCIONARIO_ID', $apuracao->FUNCIONARIO_ID)
            ->whereRaw("FORMAT(REGISTRO_DATA_HORA, 'yyyy-MM') = ?", [$apuracao->APURACAO_COMPETENCIA])
            ->orderBy('REGISTRO_DATA_HORA')
            ->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'ponto.espelho_pdf',
            compact('apuracao', 'registros')
        );

        $nome = "espelho_ponto_{$apuracao->APURACAO_COMPETENCIA}.pdf";
        return $pdf->stream($nome);
    }

    private function autorizarGestorOuSuperintendente(): void
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
            abort(403, 'Sem permissão para fechar apuração.');
        }
    }
}
