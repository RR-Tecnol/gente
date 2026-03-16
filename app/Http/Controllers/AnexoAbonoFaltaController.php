<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnexoAbonoFalta\AnexoAbonoFaltaCreateRequest;
use App\Models\AbonoFalta;
use App\Models\AnexoAbonoFalta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnexoAbonoFaltaController extends Controller
{
    public function inserir(AnexoAbonoFaltaCreateRequest $request)
    {
        $conteudo = $request->file('ANEXO_ABONO_FALTA_ARQUIVO')->get();
        $anexoAbonoFalta = new AnexoAbonoFalta($request->input());
        $anexoAbonoFalta->ANEXO_ABONO_FALTA_ARQUIVO = base64_encode($conteudo);
        $anexoAbonoFalta->ANEXO_ABONO_FALTA_EXTENSAO = $request->ANEXO_ABONO_FALTA_ARQUIVO->extension();
        $anexoAbonoFalta->save();

        return response(AbonoFalta::buscar($request->DETALHE_ESCALA_ITEM_ID), 200);
    }

    public function buscar(Request $request)
    {
        $anexoAbonoFalta = AnexoAbonoFalta::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Anexo Abono Falta id {$request->id} buscado com sucesso",
            "retorno" => $anexoAbonoFalta
        ], 200);
    }

    public function download(Request $request)
    {
        $anexoAbonoFalta = AnexoAbonoFalta::buscar($request->id);
        $anexoAbonoFalta->ANEXO_ABONO_FALTA_ARQUIVO = base64_decode($anexoAbonoFalta->ANEXO_ABONO_FALTA_ARQUIVO);

        $file = "$anexoAbonoFalta->ANEXO_ABONO_FALTA_NOME.$anexoAbonoFalta->ANEXO_ABONO_FALTA_EXTENSAO";

        Storage::disk('local')->put($file, $anexoAbonoFalta->ANEXO_ABONO_FALTA_ARQUIVO);

        $download = Storage::download($file);

        return $download;
    }
}
