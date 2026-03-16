<?php

namespace App\Models;

use App\Casts\Cpf;
use App\Mail\UsuarioMail;
use App\MyLibs\ContatoEnum;
use App\MyLibs\PerfilEnum;
use App\MyLibs\RTG;
use App\MyLibs\TipoDocumentoEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * @property integer PESSOA_ID
 * @property string PESSOA_NOME
 * @property string PESSOA_ENDERECO
 * @property integer BAIRRO_ID
 * @property string PESSOA_COMPLEMENTO
 * @property string PESSOA_CEP
 * @property string PESSOA_DATA_NASCIMENTO
 * @property integer PESSOA_ESCOLARIDADE
 * @property integer PESSOA_SEXO
 * @property integer PESSOA_ESTADO_CIVIL
 * @property integer PESSOA_TIPO_SANGUE
 * @property integer PESSOA_RH_MAIS
 * @property string PESSOA_NOME_PAI
 * @property string PESSOA_NOME_MAE
 * @property integer CIDADE_ID
 * @property integer PESSOA_STATUS
 * @property integer CIDADE_ID_NATURAL
 * @property integer PESSOA_NACIONALIDADE
 * @property integer PESSOA_RACA
 * @property integer PESSOA_GENERO
 * @property integer PESSOA_PCD
 * @property integer PESSOA_RG_NUMERO
 * @property integer PESSOA_RG_EXPEDIDOR
 * @property integer PESSOA_RG_EXPEDICAO
 * @property integer UF_ID_RG
 * @property integer PESSOA_TITULO_NUMERO
 * @property integer PESSOA_TITULO_ZONA
 * @property integer PESSOA_TITULO_SECAO
 * @property integer UF_ID_TITULO
 * @property integer PESSOA_CERTIFICADO_NUMERO
 * @property string PESSOA_CERTIFICADO_SERIE
 * @property integer PESSOA_CERTIFICADO_CATEGORIA
 * @property integer PESSOA_CERTIFICADO_ORGAO
 * @property integer UF_ID_CERTIFICADO
 * @property integer PESSOA_CNH_NUMERO
 * @property integer PESSOA_CNH_CATEGORIA
 * @property integer PESSOA_CNH_VALIDADE
 * @property integer UF_ID_CNH
 * @property integer PESSOA_CPF_NUMERO
 * @property string PESSOA_DATA_CADASTRO
 * @property integer USUARIO_ID
 *
 * @method static Pessoa find(mixed $PESSOA_ID)
 */
class Pessoa extends Model
{
    protected $table = "PESSOA";
    protected $primaryKey = "PESSOA_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "PESSOA_NOME",
        "PESSOA_ENDERECO",
        "BAIRRO_ID",
        "PESSOA_COMPLEMENTO",
        "PESSOA_CEP",
        "PESSOA_DATA_NASCIMENTO",
        "PESSOA_ESCOLARIDADE",
        "PESSOA_SEXO",
        "PESSOA_ESTADO_CIVIL",
        "PESSOA_TIPO_SANGUE",
        "PESSOA_RH_MAIS",
        "PESSOA_NOME_PAI",
        "PESSOA_NOME_MAE",
        "CIDADE_ID",
        //        "PESSOA_STATUS",
        "CIDADE_ID_NATURAL",
        "PESSOA_NACIONALIDADE",
        "PESSOA_RACA",
        "PESSOA_GENERO",
        "PESSOA_PCD",
        "PESSOA_RG_NUMERO",
        "PESSOA_RG_EXPEDIDOR",
        "PESSOA_RG_EXPEDICAO",
        "UF_ID_RG",
        "PESSOA_TITULO_NUMERO",
        "PESSOA_TITULO_ZONA",
        "PESSOA_TITULO_SECAO",
        "UF_ID_TITULO",
        "PESSOA_CERTIFICADO_NUMERO",
        "PESSOA_CERTIFICADO_SERIE",
        "PESSOA_CERTIFICADO_CATEGORIA",
        "PESSOA_CERTIFICADO_ORGAO",
        "UF_ID_CERTIFICADO",
        "PESSOA_CNH_NUMERO",
        "PESSOA_CNH_CATEGORIA",
        "PESSOA_CNH_VALIDADE",
        "UF_ID_CNH",
        "PESSOA_CPF_NUMERO",
        "PESSOA_DATA_CADASTRO",
        "USUARIO_ID",
        "PESSOA_PRE_CADASTRO",
        "PESSOA_PIS_PASEP",
    ];

    protected $casts = [
        "BAIRRO_ID" => "integer",
        "PESSOA_ESCOLARIDADE" => "integer",
        "PESSOA_SEXO" => "integer",
        "PESSOA_ESTADO_CIVIL" => "integer",
        "PESSOA_TIPO_SANGUE" => "integer",
        "PESSOA_RH_MAIS" => "integer",
        "CIDADE_ID" => "integer",

        "PESSOA_STATUS" => "integer",
        "CIDADE_ID_NATURAL" => "integer",

        "PESSOA_NACIONALIDADE" => "integer",
        "PESSOA_RACA" => "integer",
        "PESSOA_GENERO" => "integer",
        "PESSOA_PCD" => "integer",

        "PESSOA_RG_NUMERO" => "integer",

        "PESSOA_RG_EXPEDIDOR" => "integer",

        "UF_ID_RG" => "integer",

        "PESSOA_TITULO_NUMERO" => "integer",
        "PESSOA_TITULO_ZONA" => "string",
        "PESSOA_TITULO_SECAO" => "integer",
        "UF_ID_TITULO" => "integer",


        "PESSOA_CERTIFICADO_NUMERO" => "integer",

        "PESSOA_CERTIFICADO_CATEGORIA" => "integer",
        "PESSOA_CERTIFICADO_ORGAO" => "integer",
        "UF_ID_CERTIFICADO" => "integer",


        "PESSOA_CNH_NUMERO" => "integer",
        "PESSOA_CNH_CATEGORIA" => "integer",
        "UF_ID_CNH" => "integer",
        "PESSOA_CPF_NUMERO" => Cpf::class,
        "USUARIO_ID" => "integer",
        "PESSOA_PRE_CADASTRO" => "integer"
    ];

    public static $relCadPessoaView = [
        "bairro",
        "cidade.uf",
        "cidadeNatural.uf",
        "certidoes.cartorio",
        "certidoes.certidaoTipo",
        "documentos.tipoDocumento",
        "contatos.contatoTipo",
        "pessoaConselhos.conselho.tipo",
        "pessoaBancos.banco",
        "pessoaBancos.tipoPix",
        "pessoaBancos.tipoConta",
        "pessoaOcupacoes.ocupacao",
        "dependentes.dependenteTipo",
        "dependentes.dependenteTipoFim",
        "dependentes.pessoa",
        "funcionarios.lotacoes.vinculo",
        "funcionarios.lotacoes.setor.unidade",
        "funcionarios.lotacoes.atribuicaoLotacoes.atribuicao.atribuicaoTipo",
        "funcionarios.lotacoes.atribuicaoLotacoes.atribuicao.atribuicaoEscolaridade",
        "funcionarios.lotacoes.atribuicaoLotacoes.tipoCalculos",
        "funcionarios.lotacoes.atribuicaoLotacoes.programa",
        "funcionarios.lotacoes.lotacaoTipoFim",
        "funcionarios.funcionarioTipoEntrada",
        "funcionarios.funcionarioTipoSaida",
        "usuario",
    ];

    public static $relacionamentoExcluir = [
        'documentos',
        'contatos',
        'certidoes',
        'pessoaBancos',
        'pessoaConselhos',
        'pessoaOcupacoes',
        'dependentes',
        'funcionarios.lotacoes.atribuicaoLotacoes.atribuicaoLotacaoEventos',
        'funcionarios.detalheEscalas.detalheEscalaItens',
        'funcionarios.detalheEscalas.detalheEscalaAlertas',
        'funcionarios.detalheEscalas.detalheEscalaAutoriza',
        'funcionarios.usuario.usuarioPerfis',
        'funcionarios.usuario.usuarioUnidades',
    ];

    public static $relPessoaView = [
        "escolaridade",
        "sexo",
        "lotacoes.setor.unidade",
        "lotacoes.vinculo",
        "lotacoes.atribuicaoLotacoes.atribuicao",
        "usuario",
        "documentos.tipoDocumento",
        "contatos.contatoTipo",
        "pessoaBancos.banco",
        "funcionarios.detalheEscalas.escala",
    ];

    public static function relacionamentos($requisicao = null)
    {
        return [
            "bairro.cidade.uf",
            "escolaridade",
            "sexo",
            "cpf",
            "cidade.uf",
            "cidadeNatural.uf",
            "certidoes.cartorio",
            "certidoes.certidaoTipo",
            "estadoCivil",
            "tipoSanguineo",
            "rhMais",
            "dependentes.dependenteTipo",
            "dependentes.dependenteTipoFim",
            "dependentes.pessoa",
            "documentos.tipoDocumento",
            "pessoaConselhos.conselho.tipo",
            "pessoaConselhos.uf",
            "pessoaBancos.banco",
            "pessoaBancos.tipoPix",
            "pessoaBancos.tipoConta",
            "pessoaOcupacoes.ocupacao",
            "funcionarios.lotacoes.setor.unidade",
            "funcionarios.lotacoes.atribuicaoLotacoes.atribuicao.atribuicaoTipo",
            "funcionarios.lotacoes.atribuicaoLotacoes.atribuicao.atribuicaoEscolaridade",
            "funcionarios.lotacoes.atribuicoes",
            "funcionarios.lotacoes.lotacaoTipoFim",
            "funcionarios.funcionarioTipoEntrada",
            "funcionarios.funcionarioTipoSaida",
            "funcionario.pessoa",
            "funcionario.lotacoes.setor.unidade",
            "funcionario.lotacoes.vinculo",
            "funcionario.lotacoes.atribuicaoLotacoes.atribuicao",
            "funcionario.lotacoes.atribuicoes",
            "contatos.contatoTipo",
            "usuario",
        ];
    }


    public function documentos()
    {
        return $this->hasMany(Documento::class, "PESSOA_ID", "PESSOA_ID");
    }

    public function certidoes()
    {
        return $this->hasMany(Certidao::class, "PESSOA_ID", "PESSOA_ID");
    }

    public function cpf()
    {
        return $this->hasOne(Documento::class, 'PESSOA_ID', 'PESSOA_ID')->where('TIPO_DOCUMENTO_ID', 2);
    }

    public function bairro()
    {
        return $this->hasOne(Bairro::class, "BAIRRO_ID", "BAIRRO_ID");
    }

    public function escolaridade()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "PESSOA_ESCOLARIDADE")
            ->where("TABELA_ID", "=", RTG::ESCOLARIDADE)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function sexo()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "PESSOA_SEXO")
            ->where("TABELA_ID", "=", RTG::SEXO)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function cidade()
    {
        return $this->hasOne(Cidade::class, "CIDADE_ID", "CIDADE_ID");
    }

    public function cidadeNatural()
    {
        return $this->hasOne(Cidade::class, "CIDADE_ID", "CIDADE_ID_NATURAL");
    }

    public function ufRg()
    {
        return $this->hasOne(Uf::class, "UF_ID", "UF_ID_RG");
    }

    public function ufTitulo()
    {
        return $this->hasOne(Uf::class, "UF_ID", "UF_ID_TITULO");
    }

    public function ufCertificado()
    {
        return $this->hasOne(Uf::class, "UF_ID", "UF_ID_CERTIFICADO");
    }

    public function ufCnh()
    {
        return $this->hasOne(Uf::class, "UF_ID", "UF_ID_CNH");
    }

    public function estadoCivil()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "PESSOA_ESTADO_CIVIL")
            ->where("TABELA_ID", "=", RTG::ESTADO_CIVIL)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function nacionalidade()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "PESSOA_NACIONALIDADE")
            ->where("TABELA_ID", "=", RTG::PESSOA_NACIONALIDADE)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function raca()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "PESSOA_RACA")
            ->where("TABELA_ID", "=", RTG::PESSOA_RACA)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function genero()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "PESSOA_GENERO")
            ->where("TABELA_ID", "=", RTG::PESSOA_GENERO)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function pcd()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "PESSOA_PCD")
            ->where("TABELA_ID", "=", RTG::PESSOA_PCD)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function rgExpeditor()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "PESSOA_RG_EXPEDIDOR")
            ->where("TABELA_ID", "=", RTG::PESSOA_RG_EXPEDIDOR)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function certificadoCategoria()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "PESSOA_CERTIFICADO_CATEGORIA")
            ->where("TABELA_ID", "=", RTG::PESSOA_CERTIFICADO_CATEGORIA)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function certificadoOrgao()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "PESSOA_CERTIFICADO_ORGAO")
            ->where("TABELA_ID", "=", RTG::PESSOA_CERTIFICADO_ORGAO)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function cnhCategoria()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "PESSOA_CNH_CATEGORIA")
            ->where("TABELA_ID", "=", RTG::PESSOA_CNH_CATEGORIA)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function tipoSanguineo()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "PESSOA_TIPO_SANGUE")
            ->where("TABELA_ID", "=", RTG::TIPO_SANGUINEO)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function rhMais()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "PESSOA_RH_MAIS")
            ->where("TABELA_ID", "=", RTG::RH_MAIS)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function funcionarios()
    {
        return $this->hasMany(Funcionario::class, "PESSOA_ID", "PESSOA_ID")
            ->orderBy('FUNCIONARIO_ID', 'desc');
    }

    public function funcionario()
    {
        return $this->hasOne(Funcionario::class, 'PESSOA_ID', 'PESSOA_ID')
            ->whereNull('FUNCIONARIO_DATA_FIM');
    }

    public function dependentes()
    {
        return $this->hasMany(Dependente::class, "PESSOA_ID", "PESSOA_ID");
    }

    public function pessoaConselhos()
    {
        return $this->hasMany(PessoaConselho::class, "PESSOA_ID", "PESSOA_ID");
    }

    public function pessoaBancos()
    {
        return $this->hasMany(PessoaBanco::class, "PESSOA_ID", "PESSOA_ID");
    }

    public function pessoaOcupacoes()
    {
        return $this->hasMany(PessoaOcupacao::class, "PESSOA_ID", "PESSOA_ID");
    }

    public function contatos()
    {
        return $this->hasMany(Contato::class, "PESSOA_ID", "PESSOA_ID");
    }

    public function lotacoes()
    {
        return $this->hasManyThrough(Lotacao::class, Funcionario::class, 'PESSOA_ID', 'FUNCIONARIO_ID', 'PESSOA_ID', 'FUNCIONARIO_ID');
    }

    public function usuario()
    {
        return $this->hasOne(Usuario::class, "USUARIO_ID", "USUARIO_ID");
    }

    public static function listar()
    {
        return self::with(self::relacionamentos())
            ->orderBy('PESSOA_NOME')
            ->paginate();
    }

    public static function pesquisar($requisicao)
    {
        return self::with(self::relacionamentos($requisicao))
            ->when($requisicao->PESSOA_NOME, function (Builder $query) use ($requisicao) {
                return $query->where("PESSOA_NOME", "like", "%" . $requisicao->PESSOA_NOME . "%");
            })
            ->when($requisicao->PESSOA_STATUS, function (Builder $query) use ($requisicao) {
                return $query->where("PESSOA_STATUS", $requisicao->PESSOA_STATUS);
            })
            ->when($requisicao->PESSOA_ESCOLARIDADE, function (Builder $query) use ($requisicao) {
                return $query->where("PESSOA_ESCOLARIDADE", "=", $requisicao->PESSOA_ESCOLARIDADE);
            })
            ->when($requisicao->PESSOA_SEXO, function (Builder $query) use ($requisicao) {
                return $query->where("PESSOA_SEXO", "=", $requisicao->PESSOA_SEXO);
            })
            ->when($requisicao->PESSOA_CPF_NUMERO, function (Builder $query) use ($requisicao) {
                return $query->where("PESSOA_CPF_NUMERO", "=", str_replace(['.', '-'], '', $requisicao->PESSOA_CPF_NUMERO));
            })
            ->when($requisicao->lotacao, function (Builder $query) {
                return $query->whereHas("funcionarios", function (Builder $query) {
                    return $query->leftJoin('LOTACAO', 'FUNCIONARIO.FUNCIONARIO_ID', 'LOTACAO.FUNCIONARIO_ID');
                });
            })
            ->when($requisicao->VINCULO_ID, function (Builder $query) use ($requisicao) {
                $query->whereHas('funcionario.lotacoes', function ($query) use ($requisicao) {
                    $query->where('VINCULO_ID', $requisicao->VINCULO_ID);
                });
            })
            ->when($requisicao->SETOR_ID, function (Builder $query) use ($requisicao) {
                $query->whereHas("lotacoes.setor", function (Builder $j) use ($requisicao) {
                    $j->where("SETOR_ID", $requisicao->SETOR_ID);
                });
            })
            ->when($requisicao->ATRIBUICAO_ID, function (Builder $query) use ($requisicao) {
                $query->whereHas('funcionario.lotacoes.atribuicaoLotacoes', function ($query) use ($requisicao) {
                    $query->where('ATRIBUICAO_ID', $requisicao->ATRIBUICAO_ID);
                });
            })
            ->when($requisicao->funcionario, function (Builder $query) use ($requisicao) {
                $query->whereHas('funcionario', function ($query) use ($requisicao) {
                    $query->whereNotNull('FUNCIONARIO_ID');
                });
            })
            ->orderBy('PESSOA_NOME')
            ->paginate();
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamentos())
            ->find($id);
    }

    public static function getById($id, $relacionamentos = null)
    {
        return self::with($relacionamentos == null ? self::$relCadPessoaView : $relacionamentos)
            ->find($id);
    }

    public static function atualizarStatus($pessoaId)
    {

        $pessoa = self::find($pessoaId);

        $pis = Documento::with([])
            ->where("PESSOA_ID", $pessoaId)
            ->where("TIPO_DOCUMENTO_ID", TipoDocumentoEnum::PIS_PASEP_NIT)
            ->count();

        $telefone = Contato::with([])
            ->where("PESSOA_ID", $pessoaId)
            ->whereIn("CONTATO_TIPO", [ContatoEnum::TELEFONE, ContatoEnum::CELULAR])
            ->count();

        $pessoaBancoOficial = PessoaBanco::with([])
            ->where("PESSOA_ID", $pessoaId)
            ->whereHas("banco", function ($q) {
                $q->where("BANCO_OFICIAL", 1);
            })->count();

        $lotacoes = Funcionario::where("PESSOA_ID", $pessoaId)
            ->withCount('lotacoes')
            ->value('lotacoes_count');

        if ($pis > 0 && $pessoaBancoOficial > 0 && $telefone > 0 && $lotacoes > 0) {
            $pessoa->PESSOA_STATUS = 1;
            $pessoa->PESSOA_DT_ATUALIZACAO = Carbon::now();
            if (!$pessoa->PESSOA_PRE_CADASTRO) {
                $pessoa->PESSOA_LINK = 1;
            }
            self::auditarPessoa($pessoa);
        } else {
            $pessoa->PESSOA_STATUS = 0;
        }
        $pessoa->update();
    }

    protected static function auditarPessoa($pessoa)
    {
        $pessoa->PESSOA_AUDITORIA = 1;
        $pessoa->PESSOA_DT_AUDITORIA = Carbon::now();
        $pessoa->USUARIO_AUDITORIA = Auth::id();
    }

    /**
     * @deprecated Use Pessoa::excluir($pessoaId) instead.
     * Mantido apenas por compatibilidade com código legado.
     */
    public static function remover($pessoaId)
    {
        $pessoa = self::buscarExcluir($pessoaId);
        if ($pessoa) {
            return self::excluir($pessoa);
        }
        return false;
    }

    public static function search($inputs)
    {
        return self::with(self::$relPessoaView)
            ->withCount('lotacoes')
            ->when(isset($inputs['lotacoes_count']), function (Builder $q) use ($inputs) {
                $q->has('lotacoes', $inputs['lotacoes_count']);
            })
            ->when(isset($inputs["PESSOA_NOME"]) && $inputs["PESSOA_NOME"], function (Builder $q) use ($inputs) {
                $q->where("PESSOA_NOME", "like", "%{$inputs['PESSOA_NOME']}%");
            })
            ->when(isset($inputs["PESSOA_SEXO"]) && $inputs["PESSOA_SEXO"], function (Builder $q) use ($inputs) {
                $q->where("PESSOA_SEXO", $inputs['PESSOA_SEXO']);
            })
            ->when(isset($inputs["PESSOA_STATUS"]) && $inputs["PESSOA_STATUS"] == 0, function (Builder $q) {
                $q->whereNull("PESSOA_STATUS")->orWhere("PESSOA_STATUS", 0);
            })
            ->when(isset($inputs["PESSOA_STATUS"]) && $inputs["PESSOA_STATUS"] == 1, function (Builder $q) {
                $q->where("PESSOA_STATUS", 1);
            })
            ->when(isset($inputs["PESSOA_CPF_NUMERO"]), function (Builder $q) use ($inputs) {
                $q->where("PESSOA_CPF_NUMERO", str_replace(['.', '-'], '', $inputs["PESSOA_CPF_NUMERO"]));
            })
            ->when(isset($inputs["PESSOA_PRE_CADASTRO"]) && $inputs["PESSOA_PRE_CADASTRO"], function (Builder $q) use ($inputs) {
                $q->where("PESSOA_PRE_CADASTRO", $inputs['PESSOA_PRE_CADASTRO']);
            })
            ->when(isset($inputs["UNIDADE_ID"]), function (Builder $q) use ($inputs) {
                $q->whereHas("lotacoes.setor.unidade", function (Builder $j) use ($inputs) {
                    $j->where("UNIDADE_ID", $inputs['UNIDADE_ID']);
                });
            })
            ->when(isset($inputs["SETOR_ID"]), function (Builder $q) use ($inputs) {
                $q->whereHas("lotacoes.setor", function (Builder $j) use ($inputs) {
                    $j->where("SETOR_ID", $inputs['SETOR_ID']);
                });
            })
            ->when(isset($inputs["VINCULO_ID"]), function (Builder $q) use ($inputs) {
                $q->whereHas("lotacoes", function (Builder $j) use ($inputs) {
                    $j->where("VINCULO_ID", $inputs['VINCULO_ID']);
                });
            })
            ->when(isset($inputs["orderBy"]), function (Builder $query) use ($inputs) {
                $sort = isset($inputs["sort"]) ? $inputs["sort"] : 'asc';
                $query->orderBy($inputs["orderBy"], $sort);
            })
            ->when(!isset($inputs["orderBy"]), function (Builder $query) {
                $query->orderBy('PESSOA_NOME');
            })
            ->paginate();
    }

    public static function searchIncomplets($inputs)
    {
        return self::with(self::$relPessoaView)
            ->withCount('lotacoes')
            ->where("PESSOA_STATUS", 0)
            ->orderBy('PESSOA_NOME')
            ->paginate();
    }

    public static function searchPreCadastro($inputs)
    {
        return self::with(self::$relPessoaView)
            ->when(isset($inputs["PESSOA_NOME"]) && $inputs["PESSOA_NOME"], function (Builder $q) use ($inputs) {
                $q->where("PESSOA_NOME", "like", "%{$inputs['PESSOA_NOME']}%");
            })
            ->when(isset($inputs["PESSOA_CPF_NUMERO"]) && $inputs["PESSOA_CPF_NUMERO"], function (Builder $q) use ($inputs) {
                return $q->where("PESSOA_CPF_NUMERO", "=", str_replace(['.', '-'], '', $inputs['PESSOA_CPF_NUMERO']));
            })
            ->where("PESSOA_PRE_CADASTRO", 1)
            ->orderBy('PESSOA_NOME')
            ->paginate();
    }

    public static function buscarExcluir($id)
    {
        return self::with(self::$relacionamentoExcluir)
            ->find($id);
    }

    public static function excluir(Pessoa $pessoa)
    {
        // Garante que tudo necessário está carregado
        $pessoa->load(self::$relacionamentoExcluir);

        // 1. Relacionamentos diretos de Pessoa
        foreach (['documentos', 'contatos', 'certidoes', 'pessoaBancos', 'pessoaConselhos', 'pessoaOcupacoes', 'dependentes'] as $rel) {
            foreach ($pessoa->{$rel} ?? [] as $item) {
                $item->delete();
            }
        }

        // 2. Relacionamentos indiretos via Funcionario
        foreach ($pessoa->funcionarios ?? [] as $funcionario) {

            // 2.1 Lotações e atribuições
            foreach ($funcionario->lotacoes ?? [] as $lotacao) {
                foreach ($lotacao->atribuicaoLotacoes ?? [] as $atribuicao) {
                    foreach ($atribuicao->atribuicaoLotacaoEventos ?? [] as $evento) {
                        $evento->delete();
                    }
                    $atribuicao->delete();
                }
                $lotacao->delete();
            }

            // 2.2 Detalhes de Escala
            foreach ($funcionario->detalheEscalas ?? [] as $detalheEscala) {
                foreach ($detalheEscala->detalheEscalaItens ?? [] as $item) {
                    $item->delete();
                }

                foreach ($detalheEscala->detalheEscalaAlertas ?? [] as $alerta) {
                    $alerta->delete();
                }

                if ($detalheEscala->detalheEscalaAutoriza) {
                    $detalheEscala->detalheEscalaAutoriza->delete();
                }

                $detalheEscala->delete();
            }

            // 2.3 Usuários vinculados ao funcionário
            if ($funcionario->usuario) {
                foreach ($funcionario->usuario->usuarioPerfis ?? [] as $perfil) {
                    $perfil->delete();
                }

                foreach ($funcionario->usuario->usuarioUnidades ?? [] as $unidade) {
                    $unidade->delete();
                }

                $funcionario->usuario->delete();
            }

            // 3. Exclui o funcionário
            $funcionario->delete();
        }

        // 4. Por fim, exclui a própria pessoa
        $pessoa->delete();
    }

    public function getCompetenciasEscalaAttribute()
    {
        $competencias = collect();

        foreach ($this->funcionarios ?? [] as $funcionario) {
            foreach ($funcionario->detalheEscalas ?? [] as $detalhe) {
                if (isset($detalhe->escala) && $detalhe->escala->ESCALA_COMPETENCIA) {
                    // Supondo que ESCALA_COMPETENCIA vem no formato "YYYYMM" e você quer formatar para "MM/YYYY"
                    $raw = $detalhe->escala->ESCALA_COMPETENCIA;
                    if (preg_match('/^(\d{4})(\d{2})$/', $raw, $matches)) {
                        $competenciaFormatada = $matches[2] . '/' . $matches[1];
                    } else {
                        $competenciaFormatada = $raw;
                    }

                    $competencias->push($competenciaFormatada);
                }
            }
        }

        return $competencias->unique()->values();
    }

    public static function setUsuario($pessoaId)
    {
        $pessoa = Pessoa::find($pessoaId);
        $cpf = str_replace(['.', '-'], '', $pessoa->PESSOA_CPF_NUMERO);
        $usuario = null;

        if (!empty($cpf)) {
            $usuario = Usuario::where('USUARIO_CPF', $cpf)->first();
        }

        if (!$usuario) {
            // Gera senha temporária segura — usuário troca no primeiro acesso
            $senhaTemporaria = \Illuminate\Support\Str::random(10);

            $usuario = new Usuario([
                'USUARIO_NOME' => $pessoa->PESSOA_NOME,
                'USUARIO_LOGIN' => $cpf,
                'USUARIO_CPF' => $cpf,
                'USUARIO_SENHA' => \Illuminate\Support\Facades\Hash::make($senhaTemporaria),
                'USUARIO_ATIVO' => 1,
            ]);
            $usuario->save();

            $perfil = new UsuarioPerfil([
                'PERFIL_ID' => PerfilEnum::EXTERNO,
                'USUARIO_ID' => $usuario->USUARIO_ID,
                'USUARIO_PERFIL_ATIVO' => 1,
            ]);
            $perfil->save();

            // Envia e-mail com credenciais de primeiro acesso
            $emailsContato = $pessoa->contatos()->where('CONTATO_TIPO', 2)->get();
            foreach ($emailsContato as $contato) {
                Mail::to($contato->CONTATO_CONTEUDO)->send(new UsuarioMail($usuario, $senhaTemporaria));
            }
        }

        return $usuario;
    }
}
