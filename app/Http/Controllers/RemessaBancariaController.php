<?php

namespace App\Http\Controllers;

use App\Models\Folha;
use App\Services\CNAB\CNAB240Builder;
use Exception;
use Illuminate\Http\Request;

class RemessaBancariaController extends Controller
{
    protected $cnabBuilder;

    public function __construct(CNAB240Builder $cnabBuilder)
    {
        $this->cnabBuilder = $cnabBuilder;
    }

    /**
     * Rota: /remessa/{folhaId}/download
     */
    public function downloadRemessa(Request $request, $folhaId)
    {
        try {
            $folha = Folha::findOrFail($folhaId);

            // Banco default: Banco do Brasil (001)
            $bancoCodigo = $request->get('banco', '001');
            $builder = new CNAB240Builder($bancoCodigo);

            $nomeArquivo = "REMESSA_PGTO_FOLHA_{$folha->FOLHA_COMPETENCIA}_B{$bancoCodigo}.txt";

            // Fase 13.5: streaming + cursor — evita OOM em folhas grandes (~90k linhas)
            return response()->streamDownload(function () use ($builder, $folha): void {
                $out = fopen('php://output', 'wb');
                if ($out === false) {
                    return;
                }
                $builder->streamRemessa($folha, $out);
            }, $nomeArquivo, [
                'Content-Type' => 'text/plain; charset=ISO-8859-1',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'cod' => 0,
                'msg' => 'Erro ao gerar Remessa CNAB: ' . $e->getMessage()
            ], 500);
        }
    }
}
