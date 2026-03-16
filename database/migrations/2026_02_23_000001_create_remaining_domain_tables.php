<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria todas as tabelas de domínio do GENTE que estavam faltando no Docker.
 * Todas as criações usam Schema::hasTable() para não quebrar em produção.
 */
class CreateRemainingDomainTables extends Migration
{
    public function up()
    {
        // ── VINCULO (tipo de vínculo empregatício) ─────────────────────────
        if (!Schema::hasTable('VINCULO')) {
            Schema::create('VINCULO', function (Blueprint $table) {
                $table->integer('VINCULO_ID')->autoIncrement();
                $table->string('VINCULO_NOME', 100);
                $table->string('VINCULO_SIGLA', 20)->nullable();
                $table->integer('VINCULO_ATIVO')->default(1);
            });
        }

        // ── UF (estados) ───────────────────────────────────────────────────
        if (!Schema::hasTable('UF')) {
            Schema::create('UF', function (Blueprint $table) {
                $table->integer('UF_ID')->autoIncrement();
                $table->string('UF_NOME', 100);
                $table->string('UF_SIGLA', 5);
            });
        }

        // ── CIDADE ─────────────────────────────────────────────────────────
        if (!Schema::hasTable('CIDADE')) {
            Schema::create('CIDADE', function (Blueprint $table) {
                $table->integer('CIDADE_ID')->autoIncrement();
                $table->string('CIDADE_NOME', 200);
                $table->integer('UF_ID')->nullable();
                $table->string('CIDADE_CODIGO_IBGE', 20)->nullable();
            });
        }

        // ── BAIRRO ─────────────────────────────────────────────────────────
        if (!Schema::hasTable('BAIRRO')) {
            Schema::create('BAIRRO', function (Blueprint $table) {
                $table->integer('BAIRRO_ID')->autoIncrement();
                $table->string('BAIRRO_NOME', 200);
                $table->integer('CIDADE_ID')->nullable();
            });
        }

        // ── BANCO ──────────────────────────────────────────────────────────
        if (!Schema::hasTable('BANCO')) {
            Schema::create('BANCO', function (Blueprint $table) {
                $table->integer('BANCO_ID')->autoIncrement();
                $table->string('BANCO_NOME', 200);
                $table->string('BANCO_CODIGO', 10)->nullable();
                $table->integer('BANCO_ATIVO')->default(1);
            });
        }

        // ── CARTORIO ───────────────────────────────────────────────────────
        if (!Schema::hasTable('CARTORIO')) {
            Schema::create('CARTORIO', function (Blueprint $table) {
                $table->integer('CARTORIO_ID')->autoIncrement();
                $table->string('CARTORIO_NOME', 200);
                $table->integer('CIDADE_ID')->nullable();
            });
        }

        // ── CONSELHO (conselhos profissionais) ─────────────────────────────
        if (!Schema::hasTable('CONSELHO')) {
            Schema::create('CONSELHO', function (Blueprint $table) {
                $table->integer('CONSELHO_ID')->autoIncrement();
                $table->string('CONSELHO_NOME', 200);
                $table->string('CONSELHO_SIGLA', 30)->nullable();
                $table->integer('CONSELHO_ATIVO')->default(1);
            });
        }

        // ── OCUPACAO ───────────────────────────────────────────────────────
        if (!Schema::hasTable('OCUPACAO')) {
            Schema::create('OCUPACAO', function (Blueprint $table) {
                $table->integer('OCUPACAO_ID')->autoIncrement();
                $table->string('OCUPACAO_NOME', 200);
                $table->string('OCUPACAO_CBO', 20)->nullable();
                $table->integer('OCUPACAO_ATIVO')->default(1);
            });
        }

        // ── TIPO_DOCUMENTO ─────────────────────────────────────────────────
        if (!Schema::hasTable('TIPO_DOCUMENTO')) {
            Schema::create('TIPO_DOCUMENTO', function (Blueprint $table) {
                $table->integer('TIPO_DOCUMENTO_ID')->autoIncrement();
                $table->string('TIPO_DOCUMENTO_NOME', 100);
                $table->integer('TIPO_DOCUMENTO_ATIVO')->default(1);
            });
        }

        // ── TIPO_CONTATO ───────────────────────────────────────────────────
        if (!Schema::hasTable('TIPO_CONTATO')) {
            Schema::create('TIPO_CONTATO', function (Blueprint $table) {
                $table->integer('TIPO_CONTATO_ID')->autoIncrement();
                $table->string('TIPO_CONTATO_NOME', 100);
                $table->integer('TIPO_CONTATO_ATIVO')->default(1);
            });
        }

        // ── TIPO_ALERTA ────────────────────────────────────────────────────
        if (!Schema::hasTable('TIPO_ALERTA')) {
            Schema::create('TIPO_ALERTA', function (Blueprint $table) {
                $table->integer('TIPO_ALERTA_ID')->autoIncrement();
                $table->string('TIPO_ALERTA_NOME', 100);
                $table->integer('TIPO_ALERTA_ATIVO')->default(1);
            });
        }

        // ── ATRIBUICAO (cargo/função) ──────────────────────────────────────
        if (!Schema::hasTable('ATRIBUICAO')) {
            Schema::create('ATRIBUICAO', function (Blueprint $table) {
                $table->integer('ATRIBUICAO_ID')->autoIncrement();
                $table->string('ATRIBUICAO_NOME', 200);
                $table->integer('ATRIBUICAO_ATIVO')->default(1);
            });
        }

        // ── ATRIBUICAO_LOTACAO (CH de uma função em uma lotação) ───────────
        if (!Schema::hasTable('ATRIBUICAO_LOTACAO')) {
            Schema::create('ATRIBUICAO_LOTACAO', function (Blueprint $table) {
                $table->integer('ATRIBUICAO_LOTACAO_ID')->autoIncrement();
                $table->integer('ATRIBUICAO_ID');
                $table->integer('LOTACAO_ID');
                $table->integer('ATRIBUICAO_LOTACAO_CARGA_HORARIA')->nullable();
                $table->integer('ATRIBUICAO_LOTACAO_ATIVO')->default(1);
            });
        }

        // ── ATRIBUICAO_CONFIG (macro de escala por atribuição) ─────────────
        if (!Schema::hasTable('ATRIBUICAO_CONFIG')) {
            Schema::create('ATRIBUICAO_CONFIG', function (Blueprint $table) {
                $table->integer('ATRIBUICAO_CONFIG_ID')->autoIncrement();
                $table->integer('ATRIBUICAO_ID');
                $table->integer('SETOR_ID')->nullable();
                $table->integer('TURNO_ID')->nullable();
                $table->integer('ATRIBUICAO_CONFIG_ATIVO')->default(1);
            });
        }

        // ── SETOR_ATRIBUICAO ───────────────────────────────────────────────
        if (!Schema::hasTable('SETOR_ATRIBUICAO')) {
            Schema::create('SETOR_ATRIBUICAO', function (Blueprint $table) {
                $table->integer('SETOR_ATRIBUICAO_ID')->autoIncrement();
                $table->integer('SETOR_ID');
                $table->integer('ATRIBUICAO_ID');
            });
        }

        // ── TURNO ──────────────────────────────────────────────────────────
        if (!Schema::hasTable('TURNO')) {
            Schema::create('TURNO', function (Blueprint $table) {
                $table->integer('TURNO_ID')->autoIncrement();
                $table->string('TURNO_DESCRICAO', 200);
                $table->string('TURNO_SIGLA', 10)->nullable();
                $table->string('TURNO_HORA_INICIO', 10)->nullable();
                $table->string('TURNO_HORA_FIM', 10)->nullable();
                $table->integer('TURNO_CARGA_HORARIA')->nullable();
                $table->integer('TURNO_ATIVO')->default(1);
            });
        }

        // ── FERIADO ────────────────────────────────────────────────────────
        if (!Schema::hasTable('FERIADO')) {
            Schema::create('FERIADO', function (Blueprint $table) {
                $table->integer('FERIADO_ID')->autoIncrement();
                $table->date('FERIADO_DATA');
                $table->string('FERIADO_DESCRICAO', 200);
                $table->integer('UNIDADE_ID')->nullable();
                $table->integer('FERIADO_ATIVO')->default(1);
            });
        }

        // ── ESCALA ─────────────────────────────────────────────────────────
        if (!Schema::hasTable('ESCALA')) {
            Schema::create('ESCALA', function (Blueprint $table) {
                $table->integer('ESCALA_ID')->autoIncrement();
                $table->integer('SETOR_ID');
                $table->string('ESCALA_COMPETENCIA', 7); // MM/YYYY
                $table->text('ESCALA_OBSERVACAO')->nullable();
                $table->integer('ESCALA_ATIVO')->default(1);
            });
        }

        // ── HISTORICO_ESCALA ───────────────────────────────────────────────
        if (!Schema::hasTable('HISTORICO_ESCALA')) {
            Schema::create('HISTORICO_ESCALA', function (Blueprint $table) {
                $table->integer('HISTORICO_ESCALA_ID')->autoIncrement();
                $table->integer('ESCALA_ID');
                $table->integer('USUARIO_ID')->nullable();
                $table->dateTime('HISTORICO_ESCALA_DATA')->nullable();
                $table->string('HISTORICO_ESCALA_STATUS', 50)->nullable();
                $table->text('HISTORICO_ESCALA_OBSERVACAO')->nullable();
            });
        }

        // ── DETALHE_ESCALA (linha da escala por funcionário) ───────────────
        if (!Schema::hasTable('DETALHE_ESCALA')) {
            Schema::create('DETALHE_ESCALA', function (Blueprint $table) {
                $table->integer('DETALHE_ESCALA_ID')->autoIncrement();
                $table->integer('ESCALA_ID');
                $table->integer('FUNCIONARIO_ID');
                $table->integer('ATRIBUICAO_ID')->nullable();
            });
        }

        // ── DETALHE_ESCALA_ITEM (turno de um dia) ─────────────────────────
        if (!Schema::hasTable('DETALHE_ESCALA_ITEM')) {
            Schema::create('DETALHE_ESCALA_ITEM', function (Blueprint $table) {
                $table->integer('DETALHE_ESCALA_ITEM_ID')->autoIncrement();
                $table->integer('DETALHE_ESCALA_ID');
                $table->integer('TURNO_ID')->nullable();
                $table->date('DETALHE_ESCALA_ITEM_DATA');
            });
        }

        // ── DETALHE_ESCALA_ALERTA ─────────────────────────────────────────
        if (!Schema::hasTable('DETALHE_ESCALA_ALERTA')) {
            Schema::create('DETALHE_ESCALA_ALERTA', function (Blueprint $table) {
                $table->integer('DETALHE_ESCALA_ALERTA_ID')->autoIncrement();
                $table->integer('DETALHE_ESCALA_ID');
                $table->integer('TIPO_ALERTA_ID')->nullable();
                $table->text('DETALHE_ESCALA_ALERTA_DESCRICAO')->nullable();
                $table->integer('DETALHE_ESCALA_ALERTA_RESOLVIDO')->default(0);
            });
        }

        // ── SUBSTITUICAO_ESCALA ────────────────────────────────────────────
        if (!Schema::hasTable('SUBSTITUICAO_ESCALA')) {
            Schema::create('SUBSTITUICAO_ESCALA', function (Blueprint $table) {
                $table->integer('SUBSTITUICAO_ESCALA_ID')->autoIncrement();
                $table->integer('ESCALA_ID');
                $table->integer('FUNCIONARIO_ID');
                $table->integer('FUNCIONARIO_SUBSTITUTO_ID')->nullable();
                $table->date('SUBSTITUICAO_ESCALA_DATA')->nullable();
            });
        }

        // ── EVENTO (rubrica de folha) ──────────────────────────────────────
        if (!Schema::hasTable('EVENTO')) {
            Schema::create('EVENTO', function (Blueprint $table) {
                $table->integer('EVENTO_ID')->autoIncrement();
                $table->string('EVENTO_NOME', 200);
                $table->string('EVENTO_CODIGO', 20)->nullable();
                $table->integer('EVENTO_TIPO')->nullable(); // 1=proventos, 2=descontos
                $table->integer('EVENTO_ATIVO')->default(1);
            });
        }

        // ── EVENTO_VINCULO ─────────────────────────────────────────────────
        if (!Schema::hasTable('EVENTO_VINCULO')) {
            Schema::create('EVENTO_VINCULO', function (Blueprint $table) {
                $table->integer('EVENTO_VINCULO_ID')->autoIncrement();
                $table->integer('EVENTO_ID');
                $table->integer('VINCULO_ID');
                $table->integer('EVENTO_VINCULO_ATIVO')->default(1);
            });
        }

        // ── FOLHA ──────────────────────────────────────────────────────────
        if (!Schema::hasTable('FOLHA')) {
            Schema::create('FOLHA', function (Blueprint $table) {
                $table->integer('FOLHA_ID')->autoIncrement();
                $table->integer('SETOR_ID')->nullable();
                $table->string('FOLHA_COMPETENCIA', 7)->nullable(); // MM/YYYY
                $table->integer('FOLHA_STATUS')->default(1);
                $table->integer('FOLHA_ATIVO')->default(1);
            });
        }

        // ── FOLHA_SETOR ────────────────────────────────────────────────────
        if (!Schema::hasTable('FOLHA_SETOR')) {
            Schema::create('FOLHA_SETOR', function (Blueprint $table) {
                $table->integer('FOLHA_SETOR_ID')->autoIncrement();
                $table->integer('FOLHA_ID');
                $table->integer('SETOR_ID');
            });
        }

        // ── DETALHE_FOLHA (item da folha por funcionário) ─────────────────
        if (!Schema::hasTable('DETALHE_FOLHA')) {
            Schema::create('DETALHE_FOLHA', function (Blueprint $table) {
                $table->integer('DETALHE_FOLHA_ID')->autoIncrement();
                $table->integer('FOLHA_ID');
                $table->integer('FUNCIONARIO_ID');
            });
        }

        // ── EVENTO_DETALHE_FOLHA (rubricas por linha da folha) ────────────
        if (!Schema::hasTable('EVENTO_DETALHE_FOLHA')) {
            Schema::create('EVENTO_DETALHE_FOLHA', function (Blueprint $table) {
                $table->integer('EVENTO_DETALHE_FOLHA_ID')->autoIncrement();
                $table->integer('DETALHE_FOLHA_ID');
                $table->integer('EVENTO_ID');
                $table->decimal('EVENTO_DETALHE_FOLHA_VALOR', 12, 2)->nullable();
                $table->decimal('EVENTO_DETALHE_FOLHA_REFERENCIA', 10, 4)->nullable();
            });
        }

        // ── HISTORICO_FOLHA ────────────────────────────────────────────────
        if (!Schema::hasTable('HISTORICO_FOLHA')) {
            Schema::create('HISTORICO_FOLHA', function (Blueprint $table) {
                $table->integer('HISTORICO_FOLHA_ID')->autoIncrement();
                $table->integer('FOLHA_ID');
                $table->integer('USUARIO_ID')->nullable();
                $table->dateTime('HISTORICO_FOLHA_DATA')->nullable();
                $table->string('HISTORICO_FOLHA_STATUS', 50)->nullable();
            });
        }

        // ── ABONO_FALTA ────────────────────────────────────────────────────
        if (!Schema::hasTable('ABONO_FALTA')) {
            Schema::create('ABONO_FALTA', function (Blueprint $table) {
                $table->integer('ABONO_FALTA_ID')->autoIncrement();
                $table->integer('FUNCIONARIO_ID');
                $table->date('ABONO_FALTA_DATA_INICIO')->nullable();
                $table->date('ABONO_FALTA_DATA_FIM')->nullable();
                $table->integer('ABONO_FALTA_STATUS')->default(1);
                $table->text('ABONO_FALTA_OBSERVACAO')->nullable();
            });
        }

        // ── DOSSIE (prontuário de documentos) ─────────────────────────────
        if (!Schema::hasTable('DOSSIE')) {
            Schema::create('DOSSIE', function (Blueprint $table) {
                $table->integer('DOSSIE_ID')->autoIncrement();
                $table->integer('FUNCIONARIO_ID');
                $table->integer('TIPO_DOCUMENTO_ID')->nullable();
                $table->string('DOSSIE_DESCRICAO', 200)->nullable();
                $table->string('DOSSIE_ARQUIVO', 500)->nullable();
                $table->date('DOSSIE_DATA')->nullable();
                $table->integer('DOSSIE_ATIVO')->default(1);
            });
        }

        // ── CONTATO ────────────────────────────────────────────────────────
        if (!Schema::hasTable('CONTATO')) {
            Schema::create('CONTATO', function (Blueprint $table) {
                $table->integer('CONTATO_ID')->autoIncrement();
                $table->integer('PESSOA_ID');
                $table->integer('TIPO_CONTATO_ID')->nullable();
                $table->string('CONTATO_VALOR', 200);
            });
        }

        // ── DEPENDENTE ─────────────────────────────────────────────────────
        if (!Schema::hasTable('DEPENDENTE')) {
            Schema::create('DEPENDENTE', function (Blueprint $table) {
                $table->integer('DEPENDENTE_ID')->autoIncrement();
                $table->integer('FUNCIONARIO_ID');
                $table->integer('PESSOA_ID');
                $table->integer('DEPENDENTE_TIPO')->nullable();
                $table->integer('DEPENDENTE_ATIVO')->default(1);
            });
        }

        // ── DOCUMENTO ──────────────────────────────────────────────────────
        if (!Schema::hasTable('DOCUMENTO')) {
            Schema::create('DOCUMENTO', function (Blueprint $table) {
                $table->integer('DOCUMENTO_ID')->autoIncrement();
                $table->integer('PESSOA_ID');
                $table->integer('TIPO_DOCUMENTO_ID')->nullable();
                $table->string('DOCUMENTO_NUMERO', 100)->nullable();
                $table->date('DOCUMENTO_DATA_EMISSAO')->nullable();
                $table->date('DOCUMENTO_DATA_VALIDADE')->nullable();
            });
        }

        // ── CERTIDAO ───────────────────────────────────────────────────────
        if (!Schema::hasTable('CERTIDAO')) {
            Schema::create('CERTIDAO', function (Blueprint $table) {
                $table->integer('CERTIDAO_ID')->autoIncrement();
                $table->integer('PESSOA_ID');
                $table->integer('CERTIDAO_TIPO')->nullable();
                $table->string('CERTIDAO_NUMERO', 100)->nullable();
                $table->integer('CARTORIO_ID')->nullable();
                $table->date('CERTIDAO_DATA')->nullable();
            });
        }

        // ── PESSOA_BANCO ───────────────────────────────────────────────────
        if (!Schema::hasTable('PESSOA_BANCO')) {
            Schema::create('PESSOA_BANCO', function (Blueprint $table) {
                $table->integer('PESSOA_BANCO_ID')->autoIncrement();
                $table->integer('PESSOA_ID');
                $table->integer('BANCO_ID')->nullable();
                $table->string('PESSOA_BANCO_AGENCIA', 20)->nullable();
                $table->string('PESSOA_BANCO_CONTA', 30)->nullable();
                $table->integer('PESSOA_BANCO_TIPO')->nullable();
                $table->integer('PESSOA_BANCO_ATIVO')->default(1);
            });
        }

        // ── PESSOA_CONSELHO ────────────────────────────────────────────────
        if (!Schema::hasTable('PESSOA_CONSELHO')) {
            Schema::create('PESSOA_CONSELHO', function (Blueprint $table) {
                $table->integer('PESSOA_CONSELHO_ID')->autoIncrement();
                $table->integer('PESSOA_ID');
                $table->integer('CONSELHO_ID');
                $table->string('PESSOA_CONSELHO_NUMERO', 50)->nullable();
                $table->date('PESSOA_CONSELHO_VALIDADE')->nullable();
            });
        }

        // ── PESSOA_OCUPACAO ────────────────────────────────────────────────
        if (!Schema::hasTable('PESSOA_OCUPACAO')) {
            Schema::create('PESSOA_OCUPACAO', function (Blueprint $table) {
                $table->integer('PESSOA_OCUPACAO_ID')->autoIncrement();
                $table->integer('PESSOA_ID');
                $table->integer('OCUPACAO_ID');
            });
        }

        // ── USUARIO_UNIDADE ────────────────────────────────────────────────
        if (!Schema::hasTable('USUARIO_UNIDADE')) {
            Schema::create('USUARIO_UNIDADE', function (Blueprint $table) {
                $table->integer('USUARIO_UNIDADE_ID')->autoIncrement();
                $table->integer('USUARIO_ID');
                $table->integer('UNIDADE_ID');
                $table->integer('USUARIO_UNIDADE_ATIVO')->default(1);
            });
        }

        // ── USUARIO_SETOR ──────────────────────────────────────────────────
        if (!Schema::hasTable('USUARIO_SETOR')) {
            Schema::create('USUARIO_SETOR', function (Blueprint $table) {
                $table->integer('USUARIO_SETOR_ID')->autoIncrement();
                $table->integer('USUARIO_ID');
                $table->integer('SETOR_ID');
                $table->integer('USUARIO_SETOR_ATIVO')->default(1);
            });
        }

        // ── FIM_LOTACAO ────────────────────────────────────────────────────
        if (!Schema::hasTable('FIM_LOTACAO')) {
            Schema::create('FIM_LOTACAO', function (Blueprint $table) {
                $table->integer('FIM_LOTACAO_ID')->autoIncrement();
                $table->integer('LOTACAO_ID');
                $table->integer('FUNCIONARIO_ID');
                $table->date('FIM_LOTACAO_DATA')->nullable();
                $table->text('FIM_LOTACAO_MOTIVO')->nullable();
            });
        }

        // ── LOTACAO_EVENTO ─────────────────────────────────────────────────
        if (!Schema::hasTable('LOTACAO_EVENTO')) {
            Schema::create('LOTACAO_EVENTO', function (Blueprint $table) {
                $table->integer('LOTACAO_EVENTO_ID')->autoIncrement();
                $table->integer('LOTACAO_ID');
                $table->integer('EVENTO_ID');
                $table->integer('LOTACAO_EVENTO_ATIVO')->default(1);
            });
        }

        // ── COMENTARIO ─────────────────────────────────────────────────────
        if (!Schema::hasTable('COMENTARIO')) {
            Schema::create('COMENTARIO', function (Blueprint $table) {
                $table->integer('COMENTARIO_ID')->autoIncrement();
                $table->integer('FUNCIONARIO_ID');
                $table->text('COMENTARIO_TEXTO');
                $table->integer('USUARIO_ID')->nullable();
                $table->dateTime('COMENTARIO_DATA')->nullable();
            });
        }

        // ── FONTE_RECURSO ──────────────────────────────────────────────────
        if (!Schema::hasTable('FONTE_RECURSO')) {
            Schema::create('FONTE_RECURSO', function (Blueprint $table) {
                $table->integer('FONTE_RECURSO_ID')->autoIncrement();
                $table->string('FONTE_RECURSO_NOME', 200);
                $table->integer('FONTE_RECURSO_ATIVO')->default(1);
            });
        }

        // ── CARGO ──────────────────────────────────────────────────────────
        if (!Schema::hasTable('CARGO')) {
            Schema::create('CARGO', function (Blueprint $table) {
                $table->integer('CARGO_ID')->autoIncrement();
                $table->string('CARGO_NOME', 200);
                $table->integer('CARGO_ATIVO')->default(1);
            });
        }

        // ── FUNCAO ─────────────────────────────────────────────────────────
        if (!Schema::hasTable('FUNCAO')) {
            Schema::create('FUNCAO', function (Blueprint $table) {
                $table->integer('FUNCAO_ID')->autoIncrement();
                $table->string('FUNCAO_NOME', 200);
                $table->integer('FUNCAO_ATIVO')->default(1);
            });
        }

        // ── DETALHE_ESCALA_AUTORIZA ────────────────────────────────────────
        if (!Schema::hasTable('DETALHE_ESCALA_AUTORIZA')) {
            Schema::create('DETALHE_ESCALA_AUTORIZA', function (Blueprint $table) {
                $table->integer('DETALHE_ESCALA_AUTORIZA_ID')->autoIncrement();
                $table->integer('DETALHE_ESCALA_ID');
                $table->integer('USUARIO_ID')->nullable();
                $table->dateTime('DETALHE_ESCALA_AUTORIZA_DATA')->nullable();
            });
        }
    }

    public function down()
    {
        // No-op intencional — não derrubamos tabelas em produção
    }
}
