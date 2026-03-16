<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona todas as colunas que faltaram na migration inicial da tabela PESSOA.
 * A migration core só criou as colunas básicas; o Model Pessoa tem ~40 propriedades.
 * PESSOA_CPF_NUMERO é crítica — usada pelo middleware UsuarioExterno e no relacionamento
 * Usuario::pessoaVinculada() em todo request autenticado.
 */
class AddMissingColumnsToPessoa extends Migration
{
    private $columns = [
        // Coluna crítica — usada em todos os requests autenticados
        ['PESSOA_CPF_NUMERO', 'string', 20, true],
        // Endereço
        ['PESSOA_ENDERECO', 'string', 300, true],
        ['BAIRRO_ID', 'integer', null, true],
        ['PESSOA_COMPLEMENTO', 'string', 100, true],
        ['PESSOA_CEP', 'string', 20, true],
        ['CIDADE_ID', 'integer', null, true],
        ['CIDADE_ID_NATURAL', 'integer', null, true],
        // Dados pessoais
        ['PESSOA_DATA_NASCIMENTO', 'date', null, true],
        ['PESSOA_ESTADO_CIVIL', 'integer', null, true],
        ['PESSOA_TIPO_SANGUE', 'integer', null, true],
        ['PESSOA_RH_MAIS', 'integer', null, true],
        ['PESSOA_NOME_PAI', 'string', 200, true],
        ['PESSOA_NOME_MAE', 'string', 200, true],
        ['PESSOA_STATUS', 'integer', null, true],
        ['PESSOA_NACIONALIDADE', 'integer', null, true],
        ['PESSOA_RACA', 'integer', null, true],
        ['PESSOA_GENERO', 'integer', null, true],
        ['PESSOA_PCD', 'integer', null, true],
        // Documentos
        ['PESSOA_RG_NUMERO', 'string', 30, true],
        ['PESSOA_RG_EXPEDIDOR', 'string', 50, true],
        ['PESSOA_RG_EXPEDICAO', 'date', null, true],
        ['UF_ID_RG', 'integer', null, true],
        ['PESSOA_TITULO_NUMERO', 'string', 30, true],
        ['PESSOA_TITULO_ZONA', 'string', 10, true],
        ['PESSOA_TITULO_SECAO', 'string', 10, true],
        ['UF_ID_TITULO', 'integer', null, true],
        ['PESSOA_CERTIFICADO_NUMERO', 'string', 50, true],
        ['PESSOA_CERTIFICADO_SERIE', 'string', 20, true],
        ['PESSOA_CERTIFICADO_CATEGORIA', 'integer', null, true],
        ['PESSOA_CERTIFICADO_ORGAO', 'string', 100, true],
        ['UF_ID_CERTIFICADO', 'integer', null, true],
        ['PESSOA_CNH_NUMERO', 'string', 30, true],
        ['PESSOA_CNH_CATEGORIA', 'string', 5, true],
        ['PESSOA_CNH_VALIDADE', 'date', null, true],
        ['UF_ID_CNH', 'integer', null, true],
        // Outros
        ['PESSOA_DATA_CADASTRO', 'dateTime', null, true],
        ['USUARIO_ID', 'integer', null, true],
    ];

    public function up()
    {
        if (!Schema::hasTable('PESSOA'))
            return;

        Schema::table('PESSOA', function (Blueprint $table) {
            foreach ($this->columns as [$col, $type, $size, $nullable]) {
                if (Schema::hasColumn('PESSOA', $col))
                    continue;

                $def = match ($type) {
                    'string' => $table->string($col, $size ?? 255),
                    'integer' => $table->integer($col),
                    'date' => $table->date($col),
                    'dateTime' => $table->dateTime($col),
                    default => $table->string($col),
                };
                if ($nullable)
                    $def->nullable();
            }
        });
    }

    public function down()
    {
        // No-op intencional
    }
}
