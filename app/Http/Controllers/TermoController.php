<?php

namespace App\Http\Controllers;

use App\Http\Requests\Termo\TermoCreateRequest;
use App\Http\Requests\Termo\TermoUpdateRequest;
use App\Models\Termo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TermoController extends Controller
{
    public function view()
    {
        return view('termo.termo_view');
    }

    public function listar(Request $request){
        $termos = Termo::with([])
                        ->paginate();

        return response($termos);
    }
    
    public function inserir(TermoCreateRequest $request)
    {
        $conteudo = $request->file('TERMO_ARQUIVO')->get();
        $termo = new Termo($request->input());
        $termo->TERMO_ARQUIVO = base64_encode($conteudo);
        $termo->TERMO_EXTENSAO = $request->TERMO_ARQUIVO->extension();

        $termo->save();

        return response($termo, 200);
    }

    public function alterar(TermoUpdateRequest $request)
    {
        $termo = Termo::find($request->TERMO_ID);
        $termo->fill($request->input());
        if($request->file('TERMO_ARQUIVO')){
            $conteudo = $request->file('TERMO_ARQUIVO')->get();
            $termo->TERMO_ARQUIVO = base64_encode($conteudo);
            $termo->TERMO_EXTENSAO = $request->TERMO_ARQUIVO->extension();
        }

        $termo->update();

        return response($termo, 200);
    }


    public function download(Request $request)
    {
        $termo = Termo::find($request->id);
        $termo->TERMO_ARQUIVO = base64_decode($termo->TERMO_ARQUIVO);

        $file = "$termo->TERMO_NOME.$termo->TERMO_EXTENSAO";

        Storage::disk('local')->put($file, $termo->TERMO_ARQUIVO);

        $download = Storage::download($file);

        return $download;
    }

}
