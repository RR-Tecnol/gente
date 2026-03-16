<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnexoAfastamento\AnexoAfastamentoCreateRequest;
use App\Models\Afastamento;
use App\Models\AnexoAfastamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnexoAfastamentoController extends Controller
{
    public function inserir(AnexoAfastamentoCreateRequest $request)
    {
        $conteudo = $request->file('ANEXO_AFASTAMENTO_ARQUIVO')->get();
        $anexoAfastamento = new AnexoAfastamento($request->input());
        $anexoAfastamento->ANEXO_AFASTAMENTO_ARQUIVO = base64_encode($conteudo);
        $anexoAfastamento->ANEXO_AFASTAMENTO_EXTENSAO = $request->ANEXO_AFASTAMENTO_ARQUIVO->extension();
        $anexoAfastamento->save();

        return response(Afastamento::buscar($request->AFASTAMENTO_ID), 200);
    }

    public function buscar(Request $request)
    {
        $anexoAfastamento = AnexoAfastamento::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Anexo Afastamento id {$request->id} buscado com sucesso",
            "retorno" => $anexoAfastamento
        ], 200);
    }

    public function download(Request $request)
    {
        $anexo_afastamento = AnexoAfastamento::buscar($request->id);
        $anexo_afastamento->ANEXO_AFASTAMENTO_ARQUIVO = base64_decode($anexo_afastamento->ANEXO_AFASTAMENTO_ARQUIVO);

        $file = "$anexo_afastamento->ANEXO_AFASTAMENTO_NOME.$anexo_afastamento->ANEXO_AFASTAMENTO_EXTENSAO";

        Storage::disk('local')->put($file, $anexo_afastamento->ANEXO_AFASTAMENTO_ARQUIVO);

        $download = Storage::download($file);

        return $download;
    }
}
