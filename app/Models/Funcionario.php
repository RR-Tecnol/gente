<?php

namespace App\Models;

use App\Mail\UsuarioMail;
use App\MyLibs\PerfilEnum;
use App\MyLibs\RTG;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * @property integer FUNCIONARIO_ID
 * @property integer PESSOA_ID
 * @property string FUNCIONARIO_MATRICULA
 * @property string FUNCIONARIO_DATA_INICIO
 * @property string FUNCIONARIO_DATA_FIM
 * @property integer FUNCIONARIO_TIPO_ENTRADA
 * @property integer FUNCIONARIO_TIPO_SAIDA
 * @property string FUNCIONARIO_OBSERVACAO
 *
 * @method static Funcionario find($id)
 */
class Funcionario extends Model
{
    protected $table = "FUNCIONARIO";
    protected $primaryKey = "FUNCIONARIO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        "PESSOA_ID",
        "FUNCIONARIO_MATRICULA",
        "FUNCIONARIO_DATA_INICIO",
        "FUNCIONARIO_DATA_FIM",
        "FUNCIONARIO_TIPO_ENTRADA",
        "FUNCIONARIO_TIPO_SAIDA",
        "FUNCIONARIO_OBSERVACAO",
        "FUNCIONARIO_DATA_CADASTRO",
        "FUNCIONARIO_DATA_ATUALIZACAO",
        "USUARIO_ID",
    ];
    protected $casts = [
        "FUNCIONARIO_ID" => "integer",
        "PESSOA_ID" => "integer",
        "FUNCIONARIO_TIPO_ENTRADA" => "integer",
        "FUNCIONARIO_TIPO_SAIDA" => "integer",
    ];
    protected static $relacionamentos = [
        "pessoa.cpf",
        "lotacoes.vinculo",
        "lotacoes.lotacaoTipoFim",
        "lotacoes.setor.unidade.unidadePorte",
        "lotacoes.atribuicaoLotacoes.atribuicao.atribuicaoEscolaridade",
        "lotacoes.atribuicaoLotacoes.atribuicao.atribuicaoTipo",
        "lotacoes.atribuicaoLotacoes.atribuicaoLotacaoCargaHoraria",
    ];

    public function pessoa()
    {
        return $this->hasOne(Pessoa::class, "PESSOA_ID", "PESSOA_ID");
    }

    public function usuario()
    {
        return $this->hasOne(Usuario::class, 'FUNCIONARIO_ID', 'FUNCIONARIO_ID');
    }

    public function detalheEscalas()
    {
        return $this->hasMany(DetalheEscala::class, 'FUNCIONARIO_ID', 'FUNCIONARIO_ID');
    }

    public function lotacoes()
    {
        return $this->hasMany(Lotacao::class, 'FUNCIONARIO_ID', 'FUNCIONARIO_ID');
    }

    public function ferias()
    {
        return $this->hasMany(Ferias::class, 'FUNCIONARIO_ID', 'FUNCIONARIO_ID');
    }
    public function afastamentos()
    {
        return $this->hasMany(Afastamento::class, "FUNCIONARIO_ID", "FUNCIONARIO_ID");
    }

    public function funcionarioTipoEntrada()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "FUNCIONARIO_TIPO_ENTRADA")
            ->where("TABELA_ID", "=", RTG::TIPO_ENTRADA_FUNCIONARIO)
            ->where("COLUNA_ID", "!=", 0);
    }

    public function funcionarioTipoSaida()
    {
        return $this
            ->hasOne(TabelaGenerica::class, "COLUNA_ID", "FUNCIONARIO_TIPO_SAIDA")
            ->where("TABELA_ID", "=", RTG::TIPO_SAIDA_FUNCIONARIO)
            ->where("COLUNA_ID", "!=", 0);
    }


    public static function relacionamento()
    {
        return [
            "pessoa.cpf",
            "vinculo",
            "lotacoes.funcao",
            "lotacoes.unidade",
        ];
    }

    public static function listar()
    {
        return self::with(self::$relacionamentos)
            ->paginate();
    }

    public static function pesquisar($requisicao)
    {

        return self::with(self::$relacionamentos)

            ->when($requisicao->pessoa["PESSOA_NOME"], function (Builder $query) use ($requisicao) {
                $query->whereHas('pessoa', function (Builder $query) use ($requisicao) {
                    return $query->where("PESSOA_NOME", "like", "%" . $requisicao->pessoa["PESSOA_NOME"] . "%");
                });
            })
            ->when($requisicao->pessoa["cpf"]["DOCUMENTO_NUMERO"], function (Builder $query) use ($requisicao) {
                $query->whereHas('pessoa.cpf', function (Builder $query) use ($requisicao) {
                    return $query->where("DOCUMENTO_NUMERO", "like", "%" . $requisicao->pessoa["cpf"]["DOCUMENTO_NUMERO"] . "%");
                });
            })
            ->when($requisicao->FUNCIONARIO_MATRICULA, function (Builder $query) use ($requisicao) {
                return $query->where("FUNCIONARIO_MATRICULA", "like", "%$requisicao->FUNCIONARIO_MATRICULA%");
            });
    }

    public static function buscar($id)
    {
        return self::with(self::$relacionamentos)
            ->find($id);
    }

    static function search($valorPesquisa)
    {
        return self::with(self::$relacionamentos)
            ->whereHas("pessoa", function ($q) use ($valorPesquisa) {
                $q->where("PESSOA_NOME", "like", "%$valorPesquisa%");
            })->paginate();
    }

    public static function setUsuario($funcionarioId = null)
    {
        $funcionario = Funcionario::find($funcionarioId);
        $pessoa = $funcionario->pessoa;
        $usuario = $funcionario->usuario;
        $cpf = str_replace(['.', '-'], '', $pessoa->PESSOA_CPF_NUMERO);

        if (!empty($cpf)) {
            $usuario = Usuario::where('USUARIO_CPF', $cpf)->first();
        }

        if (!$usuario) {
            // Gera senha temporária aleatória segura — usuário troca no primeiro acesso
            $senhaTemporaria = \Illuminate\Support\Str::random(10);

            $usuario = new Usuario([
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID,
                'USUARIO_NOME' => $pessoa->PESSOA_NOME,
                'USUARIO_LOGIN' => $cpf,
                "USUARIO_CPF" => $cpf,
                'USUARIO_SENHA' => \Illuminate\Support\Facades\Hash::make($senhaTemporaria),
                'USUARIO_ATIVO' => 1,
            ]);
            $usuario->save();

            $perfil = new UsuarioPerfil([
                'PERFIL_ID' => PerfilEnum::EXTERNO,
                'USUARIO_ID' => $usuario->USUARIO_ID,
                'USUARIO_PERFIL_ATIVO' => 1
            ]);
            $perfil->save();

            // Envia e-mail com credenciais de primeiro acesso
            $emailsContato = $pessoa->contatos()->where('CONTATO_TIPO', 2)->get();
            foreach ($emailsContato as $contato) {
                Mail::to($contato->CONTATO_CONTEUDO)->send(new UsuarioMail($usuario, $senhaTemporaria));
            }
        } else {
            $usuario->FUNCIONARIO_ID = $funcionario->FUNCIONARIO_ID;
            $usuario->save();
        }
    }
}
