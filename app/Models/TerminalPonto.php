<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    TERMINAL_ID
 * @property int    UNIDADE_ID
 * @property string TERMINAL_NOME
 * @property string TERMINAL_TOKEN
 * @property string TERMINAL_IP
 * @property bool   TERMINAL_ATIVO
 * @property string TERMINAL_METODOS
 */
class TerminalPonto extends Model
{
    protected $table = 'TERMINAL_PONTO';
    protected $primaryKey = 'TERMINAL_ID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    protected $fillable = [
        'UNIDADE_ID',
        'TERMINAL_NOME',
        'TERMINAL_TOKEN',
        'TERMINAL_IP',
        'TERMINAL_ATIVO',
        'TERMINAL_METODOS',
    ];

    protected $casts = [
        'UNIDADE_ID' => 'integer',
        'TERMINAL_ATIVO' => 'boolean',
    ];

    public function unidade()
    {
        return $this->hasOne(Unidade::class, 'UNIDADE_ID', 'UNIDADE_ID');
    }

    public static function relacionamento()
    {
        return ['unidade'];
    }

    public static function listar()
    {
        return self::with(self::relacionamento())->orderBy('TERMINAL_NOME')->get();
    }

    public static function pesquisar(Builder $query, $req)
    {
        return self::with(self::relacionamento())
            ->when($req->UNIDADE_ID, fn($q) => $q->where('UNIDADE_ID', $req->UNIDADE_ID))
            ->when(isset($req->TERMINAL_ATIVO), fn($q) => $q->where('TERMINAL_ATIVO', $req->TERMINAL_ATIVO))
            ->orderBy('TERMINAL_NOME')
            ->get();
    }

    public static function buscar($id)
    {
        return self::with(self::relacionamento())->find($id);
    }

    /** Gera um token único de 40 chars para o terminal. */
    public static function gerarToken(): string
    {
        return bin2hex(random_bytes(20));
    }
}
