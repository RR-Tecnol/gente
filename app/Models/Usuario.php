<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = "USUARIO";
    protected $primaryKey = "USUARIO_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected static $rels = [
        "usuarioPerfis.perfil"
    ];

    protected $fillable = [
        "FUNCIONARIO_ID",
        "USUARIO_LOGIN",
        "USUARIO_SENHA",
        "USUARIO_NOME",
        "USUARIO_CPF",
        "USUARIO_EMAIL",
        "USUARIO_ATIVO",
        "USUARIO_VIGENCIA",
        "USUARIO_PRIMEIRO_ACESSO",
        "USUARIO_ALTERAR_SENHA",
    ];

    protected $casts = [
        "USUARIO_ID" => "integer",
        "FUNCIONARIO_ID" => "integer",
        "USUARIO_ATIVO" => "integer",
    ];

    protected $hidden = [
        'remember_token',
        'USUARIO_SENHA'
    ];

    public function getEmailForPasswordReset()
    {
        return $this->USUARIO_EMAIL;
    }

    public function getAuthPassword()
    {
        return $this->USUARIO_SENHA;
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes["USUARIO_SENHA"] = bcrypt($value);
    }

    public function perfilExterno()
    {
        return $this->hasOne(UsuarioPerfil::class, 'USUARIO_ID', 'USUARIO_ID')
            ->whereHas('perfil', function (Builder $query) {
                $query->where('PERFIL_NOME', 'EXTERNO');
            })
            ->where('USUARIO_PERFIL_ATIVO', 1);
    }

    public function funcionario()
    {
        return $this->hasOne(Funcionario::class, "FUNCIONARIO_ID", "FUNCIONARIO_ID");
    }

    public function usuarioUnidades()
    {
        return $this->hasMany(UsuarioUnidade::class, 'USUARIO_ID', 'USUARIO_ID');
    }

    public function usuarioSetores()
    {
        return $this->hasMany(UsuarioSetor::class, 'USUARIO_ID', 'USUARIO_ID');
    }

    public function usuarioPerfis()
    {
        return $this->hasMany(UsuarioPerfil::class, 'USUARIO_ID', 'USUARIO_ID');
    }

    public function pessoaVinculada()
    {
        // return $this->hasOne(Pessoa::class, 'USUARIO_ID', 'USUARIO_ID');
        return $this->hasOne(Pessoa::class, 'PESSOA_CPF_NUMERO', 'USUARIO_LOGIN');
    }

    public static function relacionamento()
    {
        return [
            "funcionario.pessoa.escolaridade",
            "funcionario.pessoa.sexo",
            "funcionario.pessoa.cpf",
            "usuarioUnidades.unidade.setores",
            "usuarioPerfis.perfil",
            "usuarioSetores.setor",
        ];
    }

    public static function listar($requisicao)
    {
        return self::with(self::relacionamento())
            ->when($requisicao->USUARIO_NOME, function (Builder $query) use ($requisicao) {
                return $query->where('USUARIO_NOME', 'like', "%$requisicao->USUARIO_NOME%");
            })
            ->when($requisicao->USUARIO_LOGIN, function (Builder $query) use ($requisicao) {
                return $query->where('USUARIO_LOGIN', 'like', "%$requisicao->USUARIO_LOGIN%");
            })
            ->when($requisicao->orderBy, function (Builder $query) use ($requisicao) {
                $requisicao->sort = $requisicao->sort ?: 'asc';
                $query->orderBy($requisicao->orderBy, $requisicao->sort);
            })
            ->when(!$requisicao->orderBy, function (Builder $query) {
                $query->orderBy('USUARIO_NOME');
            });
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())
            ->find($id);
    }

    public static function getById($userId)
    {
        return self::with(self::$rels)->find($userId);
    }

}
