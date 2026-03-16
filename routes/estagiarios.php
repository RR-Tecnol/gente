<?php
// routes/estagiarios.php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

Route::middleware(['auth'])->prefix('api/v3')->group(function () {

    // Listagem de contratos
    Route::get('/estagiarios', function (Request $req) {
        try {
            $q = DB::table('ESTAGIO_CONTRATO as ec')
                ->join('ESTAGIARIO as e', 'e.ESTAGIARIO_ID', '=', 'ec.ESTAGIARIO_ID')
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'ec.SETOR_ID')
                ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 'ec.UNIDADE_ID')
                ->select(
                    'ec.*',
                    'e.NOME as nome',
                    'e.CPF as cpf',
                    'e.INSTITUICAO_ENSINO as instituicao',
                    'e.AGENTE_INTEGRACAO as agente',
                    's.SETOR_NOME as setor',
                    'u.UNIDADE_NOME as secretaria'
                );
            if ($req->status)
                $q->where('ec.STATUS', $req->status);
            // Alertas de vencimento próximo (30 dias)
            $vencendo = DB::table('ESTAGIO_CONTRATO')
                ->where('STATUS', 'ATIVO')
                ->whereBetween('DATA_FIM', [now()->toDateString(), now()->addDays(30)->toDateString()])
                ->count();
            return response()->json(['contratos' => $q->paginate(30), 'vencendo_30dias' => $vencendo]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'contratos' => ['data' => []], 'vencendo_30dias' => 0]);
        }
    });

    // Cadastrar estagiário + contrato
    Route::post('/estagiarios', function (Request $req) {
        $req->validate([
            'cpf' => 'required',
            'nome' => 'required',
            'instituicao_ensino' => 'required',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date',
            'bolsa_valor' => 'required|numeric',
        ]);
        try {
            DB::beginTransaction();
            $estId = DB::table('ESTAGIARIO')->insertGetId([
                'CPF' => preg_replace('/\D/', '', $req->cpf),
                'NOME' => strtoupper($req->nome),
                'INSTITUICAO_ENSINO' => $req->instituicao_ensino,
                'AGENTE_INTEGRACAO' => $req->agente_integracao ?? 'CIEE',
                'CURSO' => $req->curso,
                'PERIODO_LETIVO' => $req->periodo_letivo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $contId = DB::table('ESTAGIO_CONTRATO')->insertGetId([
                'ESTAGIARIO_ID' => $estId,
                'SETOR_ID' => $req->setor_id,
                'UNIDADE_ID' => $req->unidade_id,
                'SUPERVISOR_ID' => $req->supervisor_id,
                'DATA_INICIO' => $req->data_inicio,
                'DATA_FIM' => $req->data_fim,
                'CARGA_HR_DIA' => $req->carga_hr_dia ?? 6,
                'BOLSA_VALOR' => $req->bolsa_valor,
                'AUXILIO_TRANSPORTE' => $req->auxilio_transporte ?? 0,
                'STATUS' => 'ATIVO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::commit();
            return response()->json(['mensagem' => 'Estagiário cadastrado.', 'estagiario_id' => $estId, 'contrato_id' => $contId]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // Atualizar status do contrato
    Route::patch('/estagiarios/{id}/status', function (Request $req, $id) {
        $req->validate(['status' => 'required|string']);
        try {
            DB::table('ESTAGIO_CONTRATO')->where('CONTRATO_ID', $id)->update(['STATUS' => $req->status, 'updated_at' => now()]);
            return response()->json(['mensagem' => 'Status atualizado.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // Registrar frequência mensal
    Route::post('/estagiarios/{id}/frequencia', function (Request $req, $id) {
        $req->validate(['mes_ref' => 'required', 'dias_presentes' => 'required|integer']);
        try {
            $contrato = DB::table('ESTAGIO_CONTRATO')->where('CONTRATO_ID', $id)->first();
            $diasUteis = 22;
            $bolsa = $contrato ? round(floatval($contrato->BOLSA_VALOR) * ($req->dias_presentes / $diasUteis), 2) : 0;
            DB::table('ESTAGIO_FREQUENCIA')->updateOrInsert(
                ['CONTRATO_ID' => $id, 'MES_REF' => $req->mes_ref],
                ['DIAS_PRESENTES' => $req->dias_presentes, 'DIAS_FALTAS' => max(0, $diasUteis - $req->dias_presentes), 'BOLSA_CALCULADA' => $bolsa, 'STATUS' => 'PENDENTE', 'updated_at' => now()]
            );
            return response()->json(['mensagem' => 'Frequência registrada.', 'bolsa_calculada' => $bolsa]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
});
