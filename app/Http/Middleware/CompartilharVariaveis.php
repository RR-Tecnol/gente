<?php

namespace App\Http\Middleware;

use App\Models\Acesso;
use App\Models\Termo;
use App\Models\Usuario;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class CompartilharVariaveis {
        public function handle(Request $request, Closure $next) {
        View::share([
            'usuario' => Usuario::getById(auth()->id()),
            "aplicacoes" => Acesso::getByUsuarioId(auth()->id()),
            'termosDisponiveis' => Termo::where("TERMO_ATIVO",1)
                                            ->whereDoesntHave('termoUsuario',function(Builder $query){
                                                $query->where('USUARIO_ID',auth()->id());
                                            })
                                            ->get(),
        ]);
        return $next($request);
    }
}
