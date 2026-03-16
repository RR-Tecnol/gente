<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int    CONFIG_ID
 * @property string CONFIG_CHAVE
 * @property string CONFIG_VALOR
 * @property string CONFIG_DESCRICAO
 * @property string CONFIG_TIPO
 * @property int    USUARIO_ID
 */
class ConfiguracaoSistema extends Model
{
    protected $table = 'CONFIGURACAO_SISTEMA';
    protected $primaryKey = 'CONFIG_ID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    protected $fillable = [
        'CONFIG_CHAVE',
        'CONFIG_VALOR',
        'CONFIG_DESCRICAO',
        'CONFIG_TIPO',
        'USUARIO_ID',
        'CONFIG_UPDATED_AT',
    ];

    // ──────────────────────────────────────────────
    //  Helpers estáticos
    // ──────────────────────────────────────────────

    /**
     * Retorna o valor de uma configuração.
     * Para BOOLEAN retorna true/false; para NUMBER retorna float; caso contrário string.
     */
    public static function get(string $chave, $default = null)
    {
        $config = Cache::remember("config_{$chave}", 300, function () use ($chave) {
            return self::where('CONFIG_CHAVE', $chave)->first();
        });

        if (!$config)
            return $default;

        return match ($config->CONFIG_TIPO) {
            'BOOLEAN' => $config->CONFIG_VALOR === '1',
            'NUMBER' => (float) $config->CONFIG_VALOR,
            default => $config->CONFIG_VALOR,
        };
    }

    /**
     * Salva o valor de uma configuração e invalida o cache.
     */
    public static function set(string $chave, $valor, ?int $usuarioId = null): void
    {
        self::where('CONFIG_CHAVE', $chave)->update([
            'CONFIG_VALOR' => (string) $valor,
            'USUARIO_ID' => $usuarioId,
            'CONFIG_UPDATED_AT' => now(),
        ]);

        Cache::forget("config_{$chave}");
    }

    /**
     * Retorna todas as configurações agrupadas para a tela de administração.
     */
    public static function listar()
    {
        return self::orderBy('CONFIG_CHAVE')->get();
    }
}
