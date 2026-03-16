<?php

namespace App\Http\Controllers;

use App\Models\TermoUsuario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TermoUsuarioController extends Controller
{
    public function inserir(Request $request)
    {
        $request->validate([
            'TERMOS' => ['required','array']
        ]);
        
        foreach($request->TERMOS as $termo){
            $termo = new TermoUsuario([
                'USUARIO_ID' => Auth::id(),
                'TERMO_ID' => $termo,
                'TERMO_USUARIO_DATA' => Carbon::now(),
            ]);
            $termo->save();
        }

        return response($termo, 200);
    }
}
