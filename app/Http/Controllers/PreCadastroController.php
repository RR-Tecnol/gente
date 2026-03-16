<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreCadastro\PreCadastroCreateRequest;
use App\Http\Requests\PreCadastro\PreCadastroUpdateRequest;
use App\Models\Atribuicao;
use App\Models\AtribuicaoLotacao;
use App\Models\Funcionario;
use App\Models\Lotacao;
use App\Models\Pessoa;
use App\Models\Setor;
use App\Models\Unidade;
use App\Models\Vinculo;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PreCadastroController extends Controller
{
    public function view()
    {
        $vinculos = Vinculo::where('VINCULO_ATIVO', 1)
            ->orderBy('VINCULO_DESCRICAO', 'ASC')
            ->get();
        $atribuicoes = Atribuicao::where('ATRIBUICAO_TIPO', 1)
            ->where('ATRIBUICAO_ATIVA', 1)
            ->orderBy('ATRIBUICAO_NOME', 'ASC')
            ->get();
        $unidades = Unidade::where('UNIDADE_ATIVA', 1)
            ->orderBy('UNIDADE_NOME', 'ASC')
            ->get();

        return view('pre_cadastro.pre_cadastro_view', compact('vinculos', 'atribuicoes', 'unidades'));
    }

    public function inserir(PreCadastroCreateRequest $request)
    {
        DB::beginTransaction();

        try {
            // Cria a pessoa
            $pessoa = new Pessoa($request->post());
            $pessoa->PESSOA_NOME = Str::upper($pessoa->PESSOA_NOME);
            $pessoa->CIDADE_ID = 1314;
            $pessoa->BAIRRO_ID = 303;
            $pessoa->USUARIO_ID = Auth::id();
            $pessoa->PESSOA_STATUS = 0;
            $pessoa->PESSOA_DATA_CADASTRO = Carbon::now();
            $pessoa->PESSOA_PRE_CADASTRO = 1;
            $pessoa->save();

            // Cria o funcionário
            $funcionario = new Funcionario();
            $funcionario->PESSOA_ID = $pessoa->PESSOA_ID;
            $funcionario->FUNCIONARIO_MATRICULA = str_pad($funcionario->PESSOA_ID, 6, 0, STR_PAD_LEFT) . "-" . $pessoa->funcionarios->count();
            $funcionario->FUNCIONARIO_DATA_CADASTRO = Carbon::now();
            $funcionario->USUARIO_ID = Auth::id();
            $funcionario->save();

            // Inicializa a variável
            $setorId = null;

            // Busca o setor com base no UNIDADE_ID e nome "GERAL"
            $setor = Setor::where('SETOR_ID', $request->SETOR_ID)
                ->first();

            if ($setor) {
                $setorId = $setor->SETOR_ID;
            } else {
                // Criando um novo setor
                $novoSetor = new Setor();
                $novoSetor->UNIDADE_ID = $request->UNIDADE_ID;
                $novoSetor->SETOR_NOME = 'GERAL';
                $novoSetor->SETOR_SIGLA = 'GERAL';
                $novoSetor->SETOR_ATIVO = 1;
                $novoSetor->save();

                $setorId = $novoSetor->SETOR_ID;
            }

            // Cria a lotação
            $lotacao = new Lotacao();
            $lotacao->FUNCIONARIO_ID = $funcionario->FUNCIONARIO_ID;
            $lotacao->VINCULO_ID = $request->VINCULO_ID;
            $lotacao->LOTACAO_DATA_INICIO = Carbon::now();
            $lotacao->SETOR_ID = $setorId;
            $lotacao->save();

            // Cria a atribuição de lotação
            $atribuicaoLotacao = new AtribuicaoLotacao();
            $atribuicaoLotacao->LOTACAO_ID = $lotacao->LOTACAO_ID;
            $atribuicaoLotacao->ATRIBUICAO_ID = $request->ATRIBUICAO_ID;
            $atribuicaoLotacao->ATRIBUICAO_LOTACAO_CARGA_HORARIA = $request->ATRIBUICAO_LOTACAO_CARGA_HORARIA;
            $atribuicaoLotacao->save();

            DB::commit();

            return response(Pessoa::buscar($pessoa->PESSOA_ID));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e);
        }
    }

    public function alterar(PreCadastroUpdateRequest $request)
    {
        DB::beginTransaction();

        try {
            // Obtém a pessoa para edição
            $pessoa = Pessoa::findOrFail($request->PESSOA_ID);

            // Atualiza os dados da pessoa
            $pessoa->update([
                'PESSOA_CPF_NUMERO' => $request->PESSOA_CPF_NUMERO,
                'PESSOA_NOME' => $request->PESSOA_NOME,
            ]);

            // Atualiza as informações da lotação
            foreach ($request->lotacoes as $lotacao) {
                $lotacaoDB = Lotacao::findOrFail($lotacao['LOTACAO_ID']);

                // --- Lógica do SETOR_ID ---
                $setorId = null;

                // Obtém o setor da lotação

                $setor = Setor::where('SETOR_ID', $request->lotacoes[0]['setor']['SETOR_ID'])
                    ->first();

                if ($setor) {
                    $setorId = $setor->SETOR_ID;
                } else {
                    // Criando um novo setor se não encontrar
                    $novoSetor = new Setor();
                    $novoSetor->UNIDADE_ID = $request->lotacoes[0]['setor']['UNIDADE_ID']; // Agora, pegando do lugar certo
                    $novoSetor->SETOR_NOME = 'GERAL';
                    $novoSetor->SETOR_SIGLA = 'GERAL';
                    $novoSetor->SETOR_ATIVO = 1;
                    $novoSetor->save();

                    $setorId = $novoSetor->SETOR_ID;
                }

                // Atualiza a lotação com o novo SETOR_ID
                $lotacaoDB->update([
                    'VINCULO_ID' => $lotacao['VINCULO_ID'],
                    'SETOR_ID' => $setorId, // Atualiza o SETOR_ID encontrado/criado
                ]);

                // Atualiza as atribuições de lotação
                foreach ($lotacao['atribuicaoLotacoes'] as $atribuicaoLotacao) {
                    $atribuicaoDB = AtribuicaoLotacao::findOrFail($atribuicaoLotacao['ATRIBUICAO_LOTACAO_ID']);
                    $atribuicaoDB->update([
                        'ATRIBUICAO_ID' => $atribuicaoLotacao['ATRIBUICAO_ID'],
                        'ATRIBUICAO_LOTACAO_CARGA_HORARIA' => $atribuicaoLotacao['ATRIBUICAO_LOTACAO_CARGA_HORARIA'],
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Pessoa atualizada com sucesso!', 'data' => $pessoa]);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e);
        }
    }
}
