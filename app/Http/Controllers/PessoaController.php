<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pessoa\PessoaCpfRequest;
use App\Http\Requests\Pessoa\PessoaCreateDependenteRequest;
use App\Http\Requests\Pessoa\PessoaCreateRequest;
use App\Http\Requests\Pessoa\PessoaRegistrarRequest;
use App\Http\Requests\Pessoa\PessoaUpdateDependenteRequest;
use App\Http\Requests\Pessoa\PessoaUpdateRequest;
use App\Models\Atribuicao;
use App\Models\Bairro;
use App\Models\Banco;
use App\Models\Cidade;
use App\Models\Contato;
use App\Models\Documento;
use App\Models\Funcionario;
use App\Models\Pessoa;
use App\Models\Programa;
use App\Models\Setor;
use App\Models\TabelaGenerica;
use App\Models\TipoDocumento;
use App\Models\Uf;
use App\Models\Unidade;
use App\Models\Usuario;
use App\Models\Vinculo;
use App\MyLibs\RTG;
use App\MyLibs\TipoDocumentoEnum;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PessoaController extends Controller
{
    public function view()
    {
        $escolaridades = TabelaGenerica::escolaridade();
        $sexos = TabelaGenerica::sexo();
        $contatos = TabelaGenerica::contato();
        $tiposDocumentos = TipoDocumento::where('TIPO_DOCUMENTO_ATIVO', '=', 1)->orderBy('TIPO_DOCUMENTO_OBRIGATORIO', 'desc')->orderBy('TIPO_DOCUMENTO_DESCRICAO')->get();
        $tipoDependentes = TabelaGenerica::tipo_depedente();
        $tipoFinalizacaoDependentes = TabelaGenerica::tipo_finalizacao_dependente();
        return view('pessoa.pessoa_view')
            ->with([
                'escolaridades' => $escolaridades,
                'sexos' => $sexos,
                'contatos' => $contatos,
                'tiposDocumentos' => $tiposDocumentos,
                'tipoDependentes' => $tipoDependentes,
                'tipoFinalizacaoDependentes' => $tipoFinalizacaoDependentes,
                'estadosCivis' => TabelaGenerica::listarColunasTabela(RTG::ESTADO_CIVIL, 1),
                'tiposSanguineos' => TabelaGenerica::listarColunasTabela(RTG::TIPO_SANGUINEO, 1),
                'rhsMais' => TabelaGenerica::listarColunasTabela(RTG::RH_MAIS, 1),
                'ufs' => Uf::all(),
                'bancos' => Banco::all(),
                'tiposContasBancarias' => TabelaGenerica::listarColunasTabela(RTG::TIPO_CONTA_BANCARIA, 1),
                'tiposPix' => TabelaGenerica::listarColunasTabela(RTG::TIPO_PIX, 1),
                'tiposContatos' => TabelaGenerica::listarColunasTabela(RTG::CONTATO_TIPO),
                'unidades' => Unidade::all(),
                'vinculos' => Vinculo::listAll(),
            ]);
    }

    public function cad_pessoa_view($pessoaId = null)
    {
        $pessoaId = (int) $pessoaId ?: 0;

        $escolaridades = TabelaGenerica::escolaridade();
        $sexos = TabelaGenerica::sexo();
        $contatos = TabelaGenerica::contato();
        $tiposDocumentos = TipoDocumento::where('TIPO_DOCUMENTO_ATIVO', '=', 1)->orderBy('TIPO_DOCUMENTO_OBRIGATORIO', 'desc')->orderBy('TIPO_DOCUMENTO_DESCRICAO')->get();
        $tipoDependentes = TabelaGenerica::tipo_depedente();
        $tipoFinalizacaoDependentes = TabelaGenerica::tipo_finalizacao_dependente();
        $usuarioLogado = Usuario::with(['usuarioUnidades.unidade.setores', 'usuarioPerfis.perfil'])->where('USUARIO_ID', Auth::id())->first();
        $bairros = Bairro::all();
        $cidades = Cidade::with(['uf'])->get();
        $tipoCalculos = TabelaGenerica::listarColunasTabela(RTG::TIPO_CALCULO);
        $programas = Programa::where('ATIVO', 1)->orderBy('DESCRICAO', 'ASC')->get();

        return view("pessoa.cad_pessoa_view")
            ->with([
                "pessoaId" => $pessoaId,
                'escolaridades' => $escolaridades,
                'sexos' => $sexos,
                'contatos' => $contatos,
                'tiposDocumentos' => $tiposDocumentos,
                'tipoDependentes' => $tipoDependentes,
                'tipoFinalizacaoDependentes' => $tipoFinalizacaoDependentes,
                'estadosCivis' => TabelaGenerica::listarColunasTabela(RTG::ESTADO_CIVIL, 1),
                'tiposSanguineos' => TabelaGenerica::listarColunasTabela(RTG::TIPO_SANGUINEO, 1),
                'rhsMais' => TabelaGenerica::listarColunasTabela(RTG::RH_MAIS, 1),
                'ufs' => Uf::all(),
                'bancos' => Banco::all(),
                'tiposContasBancarias' => TabelaGenerica::listarColunasTabela(RTG::TIPO_CONTA_BANCARIA, 1),
                'tiposPix' => TabelaGenerica::listarColunasTabela(RTG::TIPO_PIX, 1),
                'tiposContatos' => TabelaGenerica::listarColunasTabela(RTG::CONTATO_TIPO),

                'tiposEntradaFuncionario' => TabelaGenerica::listarColunasTabela(RTG::TIPO_ENTRADA_FUNCIONARIO, 1),
                'tiposSaidaFuncionario' => TabelaGenerica::listarColunasTabela(RTG::TIPO_SAIDA_FUNCIONARIO, 1),
                'setores' => Setor::listAll(),
                'vinculos' => Vinculo::listAll(),
                'atribuicoes' => Atribuicao::listAll(),
                'cargasHorariasAtribuicao' => TabelaGenerica::listarColunasTabela(RTG::ATRIBUICAO_LOTACAO_CARGA_HORARIA, 1),
                'lotacaoTiposFim' => TabelaGenerica::listarColunasTabela(RTG::LOTACAO_TIPO_FIM, 1),

                "nacionalidades" => TabelaGenerica::listarColunasTabela(RTG::PESSOA_NACIONALIDADE, 1),
                "racas" => TabelaGenerica::listarColunasTabela(RTG::PESSOA_RACA, 1),
                "generos" => TabelaGenerica::listarColunasTabela(RTG::PESSOA_GENERO, 1),
                "pcds" => TabelaGenerica::listarColunasTabela(RTG::PESSOA_PCD, 1),
                "expeditoresRg" => TabelaGenerica::listarColunasTabela(RTG::PESSOA_RG_EXPEDIDOR, 1),
                "certificadoCategorias" => TabelaGenerica::listarColunasTabela(RTG::PESSOA_CERTIFICADO_CATEGORIA, 1),
                "certificadoOrgaos" => TabelaGenerica::listarColunasTabela(RTG::PESSOA_CERTIFICADO_ORGAO, 1),
                "cnhCategorias" => TabelaGenerica::listarColunasTabela(RTG::PESSOA_CNH_CATEGORIA, 1),
                "certidoesTipos" => TabelaGenerica::listarColunasTabela(RTG::CERTIDAO_TIPO, 1),
                "usuarioLogado" => $usuarioLogado,
                'bairros' => $bairros,
                'cidades' => $cidades,
                'tipoCalculos' => $tipoCalculos,
                'programas' => $programas,
            ]);
    }

    public function create(PessoaCreateRequest $request)
    {
        DB::beginTransaction();
        $pessoa = new Pessoa($request->post());
        $pessoa->USUARIO_ID = Auth::id();
        $pessoa->PESSOA_DATA_CADASTRO = Carbon::now();
        $pessoa->save();

        Pessoa::setUsuario($pessoa->PESSOA_ID);

        DB::commit();

        return response(Pessoa::buscar($pessoa->PESSOA_ID));
    }

    public function update(PessoaUpdateRequest $request)
    {
        $pessoa = Pessoa::find($request->post('PESSOA_ID'));
        $pessoa->fill($request->post());
        $pessoa->update();
        Pessoa::atualizarStatus($pessoa->PESSOA_ID);
        return response(Pessoa::buscar($pessoa->PESSOA_ID));
    }

    public function delete(Request $request)
    {
        $request->validate(['pessoaId' => ['required', 'integer']]);
        try {
            DB::beginTransaction();
            Pessoa::remover($request->input('pessoaId'));
            DB::commit();
            return response()->noContent();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e);
        }
    }

    public function createDependente(PessoaCreateDependenteRequest $request)
    {
        try {
            DB::beginTransaction();
            $pessoa = new Pessoa($request->post());
            $pessoa->save();

            $documento = new Documento();
            $documento->TIPO_DOCUMENTO_ID = TipoDocumentoEnum::CPF;
            $documento->PESSOA_ID = $pessoa->PESSOA_ID;
            $documento->DOCUMENTO_NUMERO = $request->post('CPF');
            $documento->save();

            DB::commit();
            return response(Pessoa::buscar($pessoa->PESSOA_ID));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function updateDependente(PessoaUpdateDependenteRequest $request)
    {
        try {
            DB::beginTransaction();
            $pessoa = Pessoa::find($request->post('PESSOA_ID'));
            $pessoa->fill($request->post());
            $pessoa->save();

            $documento = Documento::with([])
                ->where('TIPO_DOCUMENTO_ID', TipoDocumentoEnum::CPF)
                ->where('PESSOA_ID', $pessoa->PESSOA_ID)
                ->first();
            if ($documento) {
                $documento->DOCUMENTO_NUMERO = $request->post('CPF');
            } else {
                $documento = new Documento();
                $documento->TIPO_DOCUMENTO_ID = TipoDocumentoEnum::CPF;
                $documento->PESSOA_ID = $pessoa->PESSOA_ID;
                $documento->DOCUMENTO_NUMERO = $request->post('CPF');
            }
            $documento->save();
            DB::commit();
            return response(Pessoa::buscar($pessoa->PESSOA_ID));
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function listar()
    {
        $pessoa = Pessoa::listar();

        return response([
            "cod" => 1,
            "msg" => "Pessoa listado com sucesso",
            "retorno" => $pessoa
        ], 200);
    }

    public function pesquisar(Request $request)
    {
        $pessoa = Pessoa::pesquisar($request);

        return response([
            "cod" => 1,
            "msg" => "Pessoa pesquisado com sucesso",
            "retorno" => $pessoa
        ], 200);
    }

    public function pesquisarPorCpf(PessoaCpfRequest $request)
    {
        $pessoa = Pessoa::where('PESSOA_CPF_NUMERO', $request->input('cpf'))->first();

        $pessoaInfo = [
            "PESSOA_ID" => $pessoa->PESSOA_ID,
            "LABEL" => $pessoa->PESSOA_NOME,
        ];

        return response([
            "cod" => 1,
            "msg" => "Pessoa pesquisado com sucesso",
            "retorno" => $pessoaInfo
        ], 200);
    }

    public function buscar(Request $request)
    {
        $pessoa = Pessoa::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Pessoa id {$request->id} buscado com sucesso",
            "retorno" => $pessoa
        ], 200);
    }

    public function deletar(Request $request)
    {
        // Rota legado /deletar que passava $request->id
        $request->merge(['pessoaId' => $request->id]);
        $this->delete($request);

        return response([
            "cod" => 1,
            "msg" => "Pessoa id {$request->id} deletada com sucesso.",
            "retorno" => []
        ], 200);
    }

    public function getPessoaById(Request $request)
    {
        return response(Pessoa::getById($request->input("pessoaId")));
    }

    public function search(Request $request)
    {
        $pessoas = Pessoa::search($request->input());

        $pessoas->getCollection()->transform(function ($pessoa) {
            $pessoa->escalas_competencia = $pessoa->competencias_escala;
            $pessoa->setRelation('funcionarios', null);
            return $pessoa;
        });

        return response($pessoas);
    }

    public function searchIncomplets(Request $request)
    {
        return response(Pessoa::searchIncomplets($request->input()));
    }

    public function searchPreCadastro(Request $request)
    {
        return response(Pessoa::searchPreCadastro($request->input()));
    }

    public function registro_view()
    {
        return view("pessoa.registro_view");
    }

    public function registro(PessoaRegistrarRequest $request)
    {
        DB::beginTransaction();
        $pessoa = new Pessoa($request->post());
        // $pessoa->USUARIO_ID = Auth::id();
        $pessoa->PESSOA_DATA_CADASTRO = Carbon::now();
        $pessoa->save();
        $contato = new Contato();
        $contato->CONTATO_TIPO = 2;
        $contato->PESSOA_ID = $pessoa->PESSOA_ID;
        $contato->CONTATO_CONTEUDO = $request->input('PESSOA_EMAIL');
        $contato->save();
        $usuario = Pessoa::setUsuario($pessoa->PESSOA_ID);
        DB::commit();
        Auth::login($usuario);

        return response(Pessoa::buscar($pessoa->PESSOA_ID));
    }
}
