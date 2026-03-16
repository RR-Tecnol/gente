<?php

namespace App\Http\Controllers;

use App\Models\TerminalPonto;
use App\MyLibs\PerfilEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TerminalPontoController extends Controller
{
    /** GET /ponto/terminais */
    public function index()
    {
        return response()->json(['retorno' => TerminalPonto::listar()]);
    }

    /** POST /ponto/terminais */
    public function store(Request $request)
    {
        $this->autorizarGestor();

        $request->validate([
            'UNIDADE_ID' => 'required|integer',
            'TERMINAL_NOME' => 'required|string|max:100',
            'TERMINAL_IP' => 'nullable|ip',
            'TERMINAL_METODOS' => 'nullable|string|max:50',
        ]);

        $terminal = TerminalPonto::create([
            'UNIDADE_ID' => $request->UNIDADE_ID,
            'TERMINAL_NOME' => $request->TERMINAL_NOME,
            'TERMINAL_TOKEN' => TerminalPonto::gerarToken(),
            'TERMINAL_IP' => $request->TERMINAL_IP,
            'TERMINAL_ATIVO' => true,
            'TERMINAL_METODOS' => $request->TERMINAL_METODOS ?? 'SENHA',
        ]);

        return response()->json([
            'retorno' => $terminal,
            'mensagem' => 'Terminal cadastrado. Guarde o token — ele não será exibido novamente.',
        ], 201);
    }

    /** PUT /ponto/terminais/{id} */
    public function update(Request $request, int $id)
    {
        $this->autorizarGestor();

        $terminal = TerminalPonto::findOrFail($id);
        $terminal->update($request->only([
            'TERMINAL_NOME',
            'TERMINAL_IP',
            'TERMINAL_ATIVO',
            'TERMINAL_METODOS',
        ]));

        return response()->json(['retorno' => $terminal, 'mensagem' => 'Terminal atualizado.']);
    }

    /** DELETE /ponto/terminais/{id} */
    public function destroy(int $id)
    {
        $this->autorizarGestor();
        TerminalPonto::findOrFail($id)->delete();
        return response()->json(['mensagem' => 'Terminal removido.']);
    }

    private function autorizarGestor(): void
    {
        $perfil = optional(Auth::user()->perfil)->PERFIL_ID;
        if (!in_array($perfil, [PerfilEnum::DESENVOLVEDOR, PerfilEnum::ADMINISTRADOR, PerfilEnum::GESTAO])) {
            abort(403, 'Sem permissão para gerenciar terminais.');
        }
    }
}
