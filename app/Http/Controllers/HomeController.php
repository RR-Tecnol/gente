<?php

namespace App\Http\Controllers;

use App\Models\TabelaGenerica;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $status = TabelaGenerica::status();
        $progressos = TabelaGenerica::progresso();
        $user = Auth::user();
        if ($user->USUARIO_SENHA == md5('SISGEP123')) {
            return redirect(route('usuario.alteracao_senha'));
        }
        return view('home', compact('status', 'progressos', 'user'));
    }
}
