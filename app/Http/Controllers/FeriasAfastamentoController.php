<?php

namespace App\Http\Controllers;

use App\Models\TabelaGenerica;

class FeriasAfastamentoController extends Controller
{
    public function view()
    {
        $tipoAfastamentos = TabelaGenerica::tipoAfastamento();

        return view('ferias_afastamento.ferias_afastamento_view', compact('tipoAfastamentos'));
    }
}
