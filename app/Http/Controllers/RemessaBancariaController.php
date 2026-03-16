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
            $this->cnabBuilder = new CNAB240Builder($bancoCodigo);

            $conteudoTxt = $this->cnabBuilder->gerarRemessa($folha);

            // Envia como Stream de Download TXT
            $nomeArquivo = "REMESSA_PGTO_FOLHA_{$folha->FOLHA_COMPETENCIA}_B{$bancoCodigo}.txt";

            return response($conteudoTxt)
                ->header('Content-Type', 'text/plain')
                ->header('Content-Disposition', "attachment; filename=\"{$nomeArquivo}\"");

        } catch (Exception $e) {
            return response()->json([
                'cod' => 0,
                'msg' => 'Erro ao gerar Remessa CNAB: ' . $e->getMessage()
            ], 500);
        }
    }
}
