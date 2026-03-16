<?php

namespace App\Http\Controllers;

use App\Http\Requests\Script\ScriptExecuteRequest;
use App\Models\Escala;
use App\Models\Pessoa;
use App\Models\Script;
use App\MyLibs\ScritpEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScriptController extends Controller
{
    private $label = 'Script';

    public function view()
    {
        return view('script.script_view');
    }

    public function listar(Request $request)
    {
        $script = Script::listar($request)->paginate();

        return response([
            'retorno' => $script,
            'msg' => "$this->label listado com sucesso",
        ], 200);
    }

    public function executarQuery(ScriptExecuteRequest $request)
    {
        $script_tipo = $request->input('script');
        $cpf = $request->input('cpf');
        $escalaId = $request->input('escalaId');

        switch ($script_tipo) {
            case ScritpEnum::DELETAR_PESSOA:
                $this->deletarPessoa($cpf);
                break;
            case ScritpEnum::DELETAR_ESCALA:
                $this->deletarEscala($escalaId);
                break;
            default:
                break;
        }
    }

    private function deletarPessoa($cpf)
    {
        DB::beginTransaction();

        try {
            $pessoa = Pessoa::where('PESSOA_CPF_NUMERO', $cpf)->firstOrFail();
            $pessoa = Pessoa::buscarExcluir($pessoa->PESSOA_ID);

            Pessoa::excluir($pessoa);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function deletarEscala($escalaId)
    {
        DB::beginTransaction();

        try {
            $escala = Escala::find($escalaId);
            $escala = Escala::buscarExcluir($escala->ESCALA_ID);

            Escala::excluir($escala);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
