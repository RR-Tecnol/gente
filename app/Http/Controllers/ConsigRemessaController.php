<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ConsigParserService;
use Illuminate\Support\Facades\Log;

class ConsigRemessaController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('CONSIG_REMESSA');

        if ($request->filled('consignataria_id')) {
            $query->where('CONSIGNATARIA_ID', $request->consignataria_id);
        }
        if ($request->filled('status')) {
            $query->where('REMESSA_STATUS', $request->status);
        }
        if ($request->filled('competencia')) {
            $query->where('REMESSA_COMPETENCIA', $request->competencia);
        }

        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function importar(Request $request, $id)
    {
        $request->validate([
            'layout_id' => 'required|integer',
            'arquivo'   => 'required|file|max:20480', // 20MB max
        ]);

        try {
            $service    = new ConsigParserService();
            $resultado  = $service->processar(
                (int) $id,
                (int) $request->layout_id,
                $request->file('arquivo')
            );

            // Gravar registro de remessa
            $remessaId = DB::table('CONSIG_REMESSA')->insertGetId([
                'CONSIGNATARIA_ID'       => $id,
                'LAYOUT_ID'              => $request->layout_id,
                'REMESSA_COMPETENCIA'    => $request->input('competencia'),
                'REMESSA_TIPO'           => 'retorno',
                'REMESSA_STATUS'         => $resultado['total_erros'] === 0 ? 'processado' : 'erro',
                'REMESSA_ARQUIVO_PATH'   => $request->file('arquivo')->getClientOriginalName(),
                'REMESSA_TOTAL_REGISTROS'=> $resultado['total_registros'],
                'REMESSA_TOTAL_VALOR'    => 0, // calculado em sprint posterior
                'REMESSA_ERROS'          => $resultado['total_erros'],
                'REMESSA_LOG'            => $resultado['log'],
                'REMESSA_OBS'            => "Importado via sistema — layout: {$resultado['layout_nome']}",
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            return response()->json([
                'remessa_id'      => $remessaId,
                'layout_nome'     => $resultado['layout_nome'],
                'total_registros' => $resultado['total_registros'],
                'total_erros'     => $resultado['total_erros'],
                'registros'       => array_slice($resultado['registros'], 0, 50), // preview: primeiros 50
                'log'             => $resultado['log'],
                'status'          => $resultado['total_erros'] === 0 ? 'processado' : 'erro',
            ]);

        } catch (\RuntimeException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('ConsigParserService falhou', ['erro' => $e->getMessage()]);
            return response()->json(['erro' => 'Erro interno ao processar arquivo.'], 500);
        }
    }

    public function gerar(Request $request)
    {
        $request->validate([
            'consignataria_id' => 'required|integer',
            'tipo'             => 'required|string|in:financeiro,cadastro,retorno-quitadas,retorno-pendentes',
            'competencia'      => 'required|string|size:6',
        ]);

        try {
            $service   = new \App\Services\ConsigGeradorService();
            $resultado = $service->gerar(
                (int) $request->consignataria_id,
                $request->tipo,
                $request->competencia
            );

            // Gravar registro de remessa gerada
            $remessaId = DB::table('CONSIG_REMESSA')->insertGetId([
                'CONSIGNATARIA_ID'        => $request->consignataria_id,
                'LAYOUT_ID'               => DB::table('LAYOUT_CONSIGNATARIA')
                                                ->where('CONSIGNATARIA_ID', $request->consignataria_id)
                                                ->where('LAYOUT_NOME', 'NEOCONSIG_' . strtoupper($request->tipo))
                                                ->value('LAYOUT_ID') ?? 0,
                'REMESSA_COMPETENCIA'     => $request->competencia,
                'REMESSA_TIPO'            => 'envio',
                'REMESSA_STATUS'          => 'gerado',
                'REMESSA_ARQUIVO_PATH'    => $resultado['nome_arquivo'],
                'REMESSA_TOTAL_REGISTROS' => $resultado['total_registros'],
                'REMESSA_TOTAL_VALOR'     => 0,
                'REMESSA_ERROS'           => 0,
                'REMESSA_OBS'             => "Gerado via sistema — layout: {$resultado['layout_nome']}",
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);

            return response($resultado['conteudo'], 200, [
                'Content-Type'        => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $resultado['nome_arquivo'] . '"',
                'X-Remessa-Id'        => $remessaId,
                'X-Total-Registros'   => $resultado['total_registros'],
            ]);

        } catch (\RuntimeException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ConsigGeradorService falhou', ['erro' => $e->getMessage()]);
            return response()->json(['erro' => 'Erro interno ao gerar arquivo.'], 500);
        }
    }

    public function download($rid)
    {
        $remessa = DB::table('CONSIG_REMESSA')->where('REMESSA_ID', $rid)->first();

        if (!$remessa) {
            return response()->json(['erro' => 'Remessa não encontrada.'], 404);
        }

        try {
            if ($remessa->REMESSA_TIPO === 'envio') {
                $service   = new \App\Services\ConsigGeradorService();
                $resultado = $service->gerar(
                    (int) $remessa->CONSIGNATARIA_ID,
                    $this->derivarTipoDoArquivo($remessa->REMESSA_ARQUIVO_PATH),
                    (string) $remessa->REMESSA_COMPETENCIA
                );

                return response($resultado['conteudo'], 200, [
                    'Content-Type'        => 'text/plain; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="' . $resultado['nome_arquivo'] . '"',
                ]);
            }

            // Retorno/importação: entregar log como txt
            $nomeLog  = 'log_remessa_' . $rid . '.txt';
            $conteudo = $remessa->REMESSA_LOG
                ?? "Remessa #{$rid} — {$remessa->REMESSA_TOTAL_REGISTROS} registros, {$remessa->REMESSA_ERROS} erros.";

            return response($conteudo, 200, [
                'Content-Type'        => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $nomeLog . '"',
            ]);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Download remessa falhou', [
                'remessa_id' => $rid,
                'erro'       => $e->getMessage(),
            ]);
            return response()->json(['erro' => 'Erro ao gerar download.'], 500);
        }
    }

    private function derivarTipoDoArquivo(?string $nomeArquivo): string
    {
        if (!$nomeArquivo) return 'financeiro';
        $tipos = ['financeiro', 'cadastro', 'retorno-quitadas', 'retorno-pendentes'];
        foreach ($tipos as $tipo) {
            if (str_contains(strtolower($nomeArquivo), str_replace('-', '_', $tipo))) {
                return $tipo;
            }
        }
        return 'financeiro';
    }
}
