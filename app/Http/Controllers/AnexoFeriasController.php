<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnexoFerias\AnexoFeriasCreateRequest;
use App\Models\AnexoFerias;
use App\Models\Ferias;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnexoFeriasController extends Controller
{
    public function inserir(AnexoFeriasCreateRequest $request)
    {
        $conteudo = $request->file('ANEXO_FERIAS_ARQUIVO')->get();
        $anexoferias = new AnexoFerias($request->input());
        $anexoferias->ANEXO_FERIAS_ARQUIVO = base64_encode($conteudo);
        $anexoferias->ANEXO_FERIAS_EXTENSAO = $request->ANEXO_FERIAS_ARQUIVO->extension();

        $anexoferias->save();

        return response(Ferias::buscar($request->FERIAS_ID), 200);
    }

    public function buscar(Request $request)
    {
        $anexoferias = AnexoFerias::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Anexo Ferias id {$request->id} buscado com sucesso",
            "retorno" => $anexoferias
        ], 200);
    }

    public function download(Request $request)
    {
        $anexo_ferias = AnexoFerias::buscar($request->id);
        $anexo_ferias->ANEXO_FERIAS_ARQUIVO = base64_decode($anexo_ferias->ANEXO_FERIAS_ARQUIVO);

        $file = "$anexo_ferias->ANEXO_FERIAS_NOME.$anexo_ferias->ANEXO_FERIAS_EXTENSAO";

        Storage::disk('local')->put($file, $anexo_ferias->ANEXO_FERIAS_ARQUIVO);

        $download = Storage::download($file);

        return $download;
    }
}
