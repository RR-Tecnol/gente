<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Escala;
use App\Models\Usuario;
use App\MyLibs\PerfilEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FaltaAtrasoController extends Controller
{
    private $label = 'FaltaAtraso';

    public function view()
    {
        return view('falta_atraso.falta_atraso_view');
    }

    public function listar(Request $request)
    {
        $usuario = Usuario::with('usuarioPerfis.perfil', 'usuarioUnidades.unidade')->find(Auth::id());
        $escalas = [];

        if ($usuario && $usuario->usuarioPerfis && $usuario->usuarioUnidades) {
            $perfilIds = $usuario->usuarioPerfis->pluck('perfil.PERFIL_ID')->toArray();
            $unidadeIds = $usuario->usuarioUnidades->where('USUARIO_UNIDADE_ATIVO', 1)->pluck('unidade.UNIDADE_ID')->toArray();

            $escalas = Escala::listar($request)
                ->whereHas('historicos', function ($query) {
                    $query->where('HISTORICO_ESCALA_STATUS', 4);
                })
                ->when(!in_array(PerfilEnum::DESENVOLVEDOR, $perfilIds) && !in_array(PerfilEnum::ADMINISTRADOR, $perfilIds), function ($query) use ($unidadeIds) {
                    $query->whereHas('setor', function ($query) use ($unidadeIds) {
                        $query->whereIn('UNIDADE_ID', $unidadeIds);
                    });
                })
                ->paginate();
        }

        return response([
            "cod" => 1,
            "msg" => "$this->label listado com sucesso",
            "retorno" => $escalas
        ], 200);
    }
}
