<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // RPPS
        Schema::create('RPPS_CONTRIBUICAO', function (Blueprint $t) {
            $t->increments('RPPS_ID');
            $t->unsignedInteger('FUNCIONARIO_ID');
            $t->string('COMPETENCIA', 7);
            $t->decimal('BASE_CALCULO', 15, 2)->default(0);
            $t->decimal('ALIQUOTA_SERVIDOR', 5, 4)->default(0.14);
            $t->decimal('VALOR_SERVIDOR', 15, 2)->default(0);
            $t->decimal('ALIQUOTA_PATRONAL', 5, 4)->default(0.28);
            $t->decimal('VALOR_PATRONAL', 15, 2)->default(0);
            $t->string('STATUS', 20)->default('PENDENTE');
            $t->unsignedInteger('FOLHA_ID')->nullable();
            $t->timestamps();
        });

        Schema::create('RPPS_BENEFICIARIO', function (Blueprint $t) {
            $t->increments('BENEFICIARIO_ID');
            $t->unsignedInteger('FUNCIONARIO_ID');
            $t->enum('TIPO', ['ATIVO', 'APOSENTADO', 'PENSIONISTA'])->default('ATIVO');
            $t->enum('STATUS', ['ATIVO', 'INATIVO'])->default('ATIVO');
            $t->date('DATA_INICIO')->nullable();
            $t->date('DATA_REVISAO')->nullable();
            $t->text('OBSERVACAO')->nullable();
            $t->timestamps();
        });

        Schema::create('RPPS_EXPORTACAO', function (Blueprint $t) {
            $t->increments('EXPORTACAO_ID');
            $t->enum('TIPO', ['DRAA', 'CADPREV', 'CENSO'])->default('CADPREV');
            $t->string('COMPETENCIA', 7);
            $t->string('ARQUIVO_PATH', 500)->nullable();
            $t->enum('STATUS', ['GERADO', 'ENVIADO', 'ERRO'])->default('GERADO');
            $t->timestamps();
        });

        // Diárias
        Schema::create('DIARIA_TABELA', function (Blueprint $t) {
            $t->increments('DIARIA_TABELA_ID');
            $t->string('CARGO_NIVEL', 50)->nullable();
            $t->enum('DESTINO_TIPO', ['CAPITAL_MA', 'OUTRA_CAPITAL', 'INTERIOR_MA', 'FORA_MA', 'EXTERIOR'])->default('CAPITAL_MA');
            $t->decimal('VALOR_DIARIA', 10, 2)->default(0);
            $t->date('VIGENCIA_INICIO');
            $t->date('VIGENCIA_FIM')->nullable();
        });

        Schema::create('DIARIA_SOLICITACAO', function (Blueprint $t) {
            $t->increments('SOLICITACAO_ID');
            $t->unsignedInteger('FUNCIONARIO_ID');
            $t->string('DESTINO', 200);
            $t->enum('DESTINO_TIPO', ['CAPITAL_MA', 'OUTRA_CAPITAL', 'INTERIOR_MA', 'FORA_MA', 'EXTERIOR'])->default('CAPITAL_MA');
            $t->text('OBJETIVO');
            $t->date('DATA_IDA');
            $t->date('DATA_VOLTA');
            $t->decimal('QTDE_DIARIAS', 5, 1)->default(0);
            $t->decimal('VALOR_TOTAL', 15, 2)->default(0);
            $t->string('PORTARIA_NUM', 100)->nullable();
            $t->enum('STATUS', ['PENDENTE', 'APROVADA', 'NEGADA', 'PAGA', 'PRESTACAO_PENDENTE', 'CONCLUIDA'])->default('PENDENTE');
            $t->unsignedInteger('APROVADO_POR')->nullable();
            $t->timestamps();
        });

        Schema::create('DIARIA_PRESTACAO', function (Blueprint $t) {
            $t->increments('PRESTACAO_ID');
            $t->unsignedInteger('SOLICITACAO_ID');
            $t->string('COMPROVANTE_PATH', 500)->nullable();
            $t->decimal('VALOR_GASTO', 15, 2)->default(0);
            $t->decimal('SALDO_DEVOLVIDO', 15, 2)->default(0);
            $t->date('DATA_PRESTACAO')->nullable();
            $t->text('OBSERVACAO')->nullable();
            $t->timestamps();
        });

        // Estagiários
        Schema::create('ESTAGIARIO', function (Blueprint $t) {
            $t->increments('ESTAGIARIO_ID');
            $t->unsignedInteger('PESSOA_ID')->nullable();
            $t->string('CPF', 11)->unique();
            $t->string('NOME', 150);
            $t->string('INSTITUICAO_ENSINO', 150);
            $t->enum('AGENTE_INTEGRACAO', ['CIEE', 'IEL', 'NUBE', 'DIRETO'])->default('CIEE');
            $t->string('CURSO', 150)->nullable();
            $t->string('PERIODO_LETIVO', 20)->nullable();
            $t->timestamps();
        });

        Schema::create('ESTAGIO_CONTRATO', function (Blueprint $t) {
            $t->increments('CONTRATO_ID');
            $t->unsignedInteger('ESTAGIARIO_ID');
            $t->unsignedInteger('SETOR_ID')->nullable();
            $t->unsignedInteger('UNIDADE_ID')->nullable();
            $t->unsignedInteger('SUPERVISOR_ID')->nullable();
            $t->date('DATA_INICIO');
            $t->date('DATA_FIM');
            $t->decimal('CARGA_HR_DIA', 4, 1)->default(6);
            $t->decimal('BOLSA_VALOR', 10, 2)->default(0);
            $t->decimal('AUXILIO_TRANSPORTE', 10, 2)->default(0);
            $t->integer('RENOVACOES')->default(0);
            $t->enum('STATUS', ['ATIVO', 'CONCLUIDO', 'RESCINDIDO'])->default('ATIVO');
            $t->timestamps();
        });

        Schema::create('ESTAGIO_FREQUENCIA', function (Blueprint $t) {
            $t->increments('FREQUENCIA_ID');
            $t->unsignedInteger('CONTRATO_ID');
            $t->string('MES_REF', 7);
            $t->integer('DIAS_PRESENTES')->default(0);
            $t->integer('DIAS_FALTAS')->default(0);
            $t->decimal('BOLSA_CALCULADA', 10, 2)->default(0);
            $t->enum('STATUS', ['PENDENTE', 'APROVADO', 'PAGO'])->default('PENDENTE');
            $t->timestamps();
        });

        // SAGRES
        Schema::create('SAGRES_EVENTO_DEPARA', function (Blueprint $t) {
            $t->increments('DEPARA_ID');
            $t->string('EVENTO_INTERNO_COD', 20)->nullable();
            $t->string('EVENTO_INTERNO_NOME', 100)->nullable();
            $t->string('SAGRES_COD', 10);
            $t->string('SAGRES_DESCRICAO', 100);
            $t->enum('TIPO', ['P', 'D'])->default('P');
            $t->boolean('ATIVO')->default(true);
            $t->timestamps();
        });

        Schema::create('SAGRES_EXPORTACAO', function (Blueprint $t) {
            $t->increments('EXPORTACAO_ID');
            $t->string('COMPETENCIA', 7);
            $t->string('ARQUIVO_XML_PATH', 500)->nullable();
            $t->enum('STATUS', ['GERADO', 'VALIDADO', 'ENVIADO', 'ERRO'])->default('GERADO');
            $t->text('VALIDACAO_ERROS')->nullable();
            $t->timestamp('ENVIADO_EM')->nullable();
            $t->timestamps();
        });

        // Acumulação de Cargos
        Schema::create('ACUMULACAO_DECLARACAO', function (Blueprint $t) {
            $t->increments('ACUMULACAO_ID');
            $t->unsignedInteger('FUNCIONARIO_ID');
            $t->string('ORGAO_EXTERNO', 150);
            $t->string('CARGO_EXTERNO', 100);
            $t->decimal('CARGA_HR', 5, 1)->default(0);
            $t->time('HORARIO_INICIO')->nullable();
            $t->time('HORARIO_FIM')->nullable();
            $t->date('DATA_DECLARACAO');
            $t->enum('STATUS_ANALISE', ['PENDENTE', 'APROVADO', 'IRREGULAR', 'SUSPENSO'])->default('PENDENTE');
            $t->text('OBSERVACAO_ANALISE')->nullable();
            $t->timestamps();
        });

        // Transparência
        Schema::create('TRANSPARENCIA_EXPORTACAO', function (Blueprint $t) {
            $t->increments('EXPORTACAO_ID');
            $t->enum('TIPO', ['FOLHA_CSV', 'FOLHA_XML', 'DIARIAS_CSV', 'DESPESAS_CSV'])->default('FOLHA_CSV');
            $t->string('COMPETENCIA', 7);
            $t->string('ARQUIVO_PATH', 500)->nullable();
            $t->timestamp('PUBLICADO_EM')->nullable();
            $t->enum('STATUS', ['PENDENTE', 'GERADO', 'PUBLICADO'])->default('PENDENTE');
            $t->timestamps();
        });

        // PSS
        Schema::create('PSS_EDITAL', function (Blueprint $t) {
            $t->increments('EDITAL_ID');
            $t->string('TITULO', 200);
            $t->enum('TIPO', ['PSS', 'CONCURSO'])->default('PSS');
            $t->unsignedInteger('SECRETARIA_ID')->nullable();
            $t->date('DATA_ABERTURA')->nullable();
            $t->date('DATA_FECHAMENTO')->nullable();
            $t->string('NUMERO_EDITAL', 50)->nullable();
            $t->enum('STATUS', ['RASCUNHO', 'PUBLICADO', 'ENCERRADO', 'CANCELADO'])->default('RASCUNHO');
            $t->text('OBSERVACOES')->nullable();
            $t->timestamps();
        });

        Schema::create('PSS_VAGA', function (Blueprint $t) {
            $t->increments('VAGA_ID');
            $t->unsignedInteger('EDITAL_ID');
            $t->string('CARGO', 100);
            $t->string('LOTACAO', 150)->nullable();
            $t->integer('VAGAS')->default(1);
            $t->integer('VAGAS_RESERVA_PCD')->default(0);
            $t->decimal('SALARIO', 10, 2)->default(0);
        });

        Schema::create('PSS_CANDIDATO', function (Blueprint $t) {
            $t->increments('CANDIDATO_ID');
            $t->string('CPF', 11);
            $t->string('NOME', 150);
            $t->unsignedInteger('EDITAL_ID');
            $t->unsignedInteger('VAGA_ID')->nullable();
            $t->string('INSCRICAO_NUM', 30)->nullable();
            $t->decimal('NOTA_FINAL', 5, 2)->nullable();
            $t->integer('CLASSIFICACAO')->nullable();
            $t->enum('STATUS', ['INSCRITO', 'APROVADO', 'CONVOCADO', 'NOMEADO', 'DESISTIU', 'ELIMINADO'])->default('INSCRITO');
            $t->timestamps();
        });

        // Terceirizados
        Schema::create('TERCEIRO_EMPRESA', function (Blueprint $t) {
            $t->increments('EMPRESA_ID');
            $t->string('RAZAO_SOCIAL', 200);
            $t->string('CNPJ', 18)->unique();
            $t->string('CONTRATO_NUM', 50)->nullable();
            $t->date('VIGENCIA_INICIO')->nullable();
            $t->date('VIGENCIA_FIM')->nullable();
            $t->decimal('VALOR_MENSAL', 15, 2)->default(0);
            $t->unsignedInteger('FISCAL_ID')->nullable();
            $t->enum('STATUS', ['ATIVO', 'ENCERRADO', 'SUSPENSO'])->default('ATIVO');
            $t->timestamps();
        });

        Schema::create('TERCEIRO_POSTO', function (Blueprint $t) {
            $t->increments('POSTO_ID');
            $t->unsignedInteger('EMPRESA_ID');
            $t->string('FUNCAO', 100);
            $t->string('LOCALIDADE', 200)->nullable();
            $t->string('TURNO', 50)->nullable();
            $t->string('TRABALHADOR_NOME', 150)->nullable();
            $t->string('TRABALHADOR_CPF', 11)->nullable();
        });

        Schema::create('TERCEIRO_CHECKLIST', function (Blueprint $t) {
            $t->increments('CHECKLIST_ID');
            $t->unsignedInteger('EMPRESA_ID');
            $t->string('COMPETENCIA', 7);
            $t->string('ITEM', 100);
            $t->boolean('STATUS_OK')->default(false);
            $t->string('COMPROVANTE_PATH', 500)->nullable();
            $t->decimal('GLOSA_VALOR', 15, 2)->default(0);
            $t->timestamps();
        });

        // seeds SAGRES
        DB::table('SAGRES_EVENTO_DEPARA')->insert([
            ['SAGRES_COD' => '001', 'SAGRES_DESCRICAO' => 'Salário Base', 'TIPO' => 'P', 'ATIVO' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['SAGRES_COD' => '010', 'SAGRES_DESCRICAO' => 'Gratificação / Adicional', 'TIPO' => 'P', 'ATIVO' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['SAGRES_COD' => '020', 'SAGRES_DESCRICAO' => 'Hora Extra', 'TIPO' => 'P', 'ATIVO' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['SAGRES_COD' => '030', 'SAGRES_DESCRICAO' => 'Verba Indenizatória', 'TIPO' => 'P', 'ATIVO' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['SAGRES_COD' => '040', 'SAGRES_DESCRICAO' => 'Décimo Terceiro Salário', 'TIPO' => 'P', 'ATIVO' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['SAGRES_COD' => '101', 'SAGRES_DESCRICAO' => 'IRRF', 'TIPO' => 'D', 'ATIVO' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['SAGRES_COD' => '102', 'SAGRES_DESCRICAO' => 'Contribuição Previdenciária (RPPS/IPAM)', 'TIPO' => 'D', 'ATIVO' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['SAGRES_COD' => '103', 'SAGRES_DESCRICAO' => 'Consignação em Folha', 'TIPO' => 'D', 'ATIVO' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['SAGRES_COD' => '110', 'SAGRES_DESCRICAO' => 'Desconto Diverso', 'TIPO' => 'D', 'ATIVO' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        foreach (['TERCEIRO_CHECKLIST', 'TERCEIRO_POSTO', 'TERCEIRO_EMPRESA', 'PSS_CANDIDATO', 'PSS_VAGA', 'PSS_EDITAL', 'TRANSPARENCIA_EXPORTACAO', 'ACUMULACAO_DECLARACAO', 'SAGRES_EXPORTACAO', 'SAGRES_EVENTO_DEPARA', 'ESTAGIO_FREQUENCIA', 'ESTAGIO_CONTRATO', 'ESTAGIARIO', 'DIARIA_PRESTACAO', 'DIARIA_SOLICITACAO', 'DIARIA_TABELA', 'RPPS_EXPORTACAO', 'RPPS_BENEFICIARIO', 'RPPS_CONTRIBUICAO'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
