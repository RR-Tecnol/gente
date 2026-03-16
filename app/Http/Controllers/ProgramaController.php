<?php

namespace App\Http\Controllers;

use App\Http\Requests\Programa\ProgramaCreateRequest;
use App\Http\Requests\Programa\ProgramaUpdateRequest;
use App\Models\Banco;
use App\Models\FonteRecurso;
use App\Models\Programa;
use Illuminate\Http\Request;

class ProgramaController extends Controller
{
    private $label = 'Programa';

    public function view()
    {

        $bancos = Banco::where('BANCO_ATIVO', 1)->get();
        $fonteRecursos = FonteRecurso::all();

        return view('programa.programa_view', compact('bancos', 'fonteRecursos'));
    }

    public function inserir(ProgramaCreateRequest $request)
    {
        $programa = new Programa($request->input());
        $programa->save();

        return response([
            "cod" => 1,
            "msg" => "$this->label adicionado com sucesso",
            "retorno" => $programa
        ], 200);
    }

    public function listar(Request $request)
    {
        $programas = Programa::listar($request)->paginate();

        return response([
            'retorno' => $programas,
            'msg' => "$this->label listado com sucesso",
        ], 200);
    }

    public function buscar(Request $request)
    {
        $programa = Programa::buscar($request->id);

        return response([
            'retorno' => $programa,
            'msg' => "$this->label buscado com sucesso",
        ], 200);
    }

    public function alterar(ProgramaUpdateRequest $request)
    {
        $programa = Programa::buscar($request->PROGRAMA_ID);
        $programa->fill($request->input());
        $programa->update();

        return response([
            'retorno' => $programa,
            'msg' => "$this->label alterado com sucesso",
        ], 200);
    }
}
