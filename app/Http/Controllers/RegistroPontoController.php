<?php

namespace App\Http\Controllers;

use App\Models\RegistroPonto;
use App\Services\AfdParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegistroPontoController extends Controller
{
    /** GET /ponto
     * Lista registros com filtros. */
    public function index(Request $request)
    {
        return response()->json([
            'retorno' => RegistroPonto::pesquisar($request),
        ]);
    }

    /** POST /ponto/registros — Registro manual */
    public function store(Request $request)
    {
        $request->validate([
            'FUNCIONARIO_ID' => 'required|integer',
            'REGISTRO_DATA_HORA' => 'required|date',
            'REGISTRO_TIPO' => 'required|in:ENTRADA,PAUSA,RETORNO,SAIDA',
        ]);

        $registro = RegistroPonto::create([
            'FUNCIONARIO_ID' => $request->FUNCIONARIO_ID,
            'TERMINAL_ID' => null,
            'REGISTRO_DATA_HORA' => $request->REGISTRO_DATA_HORA,
            'REGISTRO_TIPO' => $request->REGISTRO_TIPO,
            'REGISTRO_ORIGEM' => 'MANUAL',
            'REGISTRO_OBSERVACAO' => $request->REGISTRO_OBSERVACAO,
        ]);

        return response()->json(['retorno' => $registro, 'mensagem' => 'Registro inserido.'], 201);
    }

    /** POST /ponto/registros/afd — Import arquivo AFD */
    public function importarAfd(Request $request)
    {
        $request->validate([
            'arquivo' => 'required|file|mimes:txt,afd',
            'funcionario_id' => 'required|integer',
        ]);

        $conteudo = file_get_contents($request->file('arquivo')->getRealPath());
        $parser = new AfdParserService();

        // Valida integridade do arquivo
        if (!$parser->validar($conteudo)) {
            return response()->json([
                'erro' => 'Arquivo AFD com inconsistências',
                'erros' => $parser->getErros(),
            ], 422);
        }

        $dados = $parser->parsear($conteudo, (int) $request->funcionario_id);

        if (empty($dados)) {
            return response()->json(['mensagem' => 'Nenhum registro encontrado no arquivo.']);
        }

        $inseridos = 0;
        foreach ($dados as $d) {
            // Evita duplicidade pelo NSR
            $existe = RegistroPonto::where('FUNCIONARIO_ID', $d['FUNCIONARIO_ID'])
                ->where('REGISTRO_NSR', $d['REGISTRO_NSR'])
                ->exists();
            if (!$existe) {
                RegistroPonto::create($d);
                $inseridos++;
            }
        }

        return response()->json([
            'mensagem' => "$inseridos registro(s) importado(s) com sucesso.",
            'total' => count($dados),
            'inseridos' => $inseridos,
        ]);
    }

    /** DELETE /ponto/registros/{id} — Só remove registros MANUAIS */
    public function destroy(int $id)
    {
        $registro = RegistroPonto::findOrFail($id);
        if ($registro->REGISTRO_ORIGEM !== 'MANUAL') {
            return response()->json(['erro' => 'Só é possível excluir registros manuais.'], 403);
        }
        $registro->delete();
        return response()->json(['mensagem' => 'Registro removido.']);
    }
}
