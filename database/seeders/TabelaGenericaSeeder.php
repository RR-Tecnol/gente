<?php

namespace Database\Seeders;

use Database\Seeders\tgs\TgAtribuicaoLotacaoCargaHoraria;
use Database\Seeders\tgs\TgContatoTipo;
use Database\Seeders\tgs\TgDocumentosPessoais;
use Database\Seeders\tgs\TgEscolaridade;
use Database\Seeders\tgs\TgEstadoCivil;
use Database\Seeders\tgs\TgFatorRh;
use Database\Seeders\tgs\TgHistorico;
use Database\Seeders\tgs\TgLotacaoTipoFim;
use Database\Seeders\tgs\TgMotivo;
use Database\Seeders\tgs\TgMotivoFimDependencia;
use Database\Seeders\tgs\TgSexo;
use Database\Seeders\tgs\TgStatus;
use Database\Seeders\tgs\TgTipoAtribuicao;
use Database\Seeders\tgs\TgTipoConselhoClasse;
use Database\Seeders\tgs\TgTipoContaBancaria;
use Database\Seeders\tgs\TgTipoDependente;
use Database\Seeders\tgs\TgTipoEntradaFuncionario;
use Database\Seeders\tgs\TgTipoPix;
use Database\Seeders\tgs\TgTiposAfastamento;
use Database\Seeders\tgs\TgTipoSaidaFuncionario;
use Database\Seeders\tgs\TgTipoSanguineo;
use Database\Seeders\tgs\TgTipoStatusScala;
use Database\Seeders\tgs\TgTipoUnidade;
use Database\Seeders\tgs\TgUnidadePorte;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TabelaGenericaSeeder extends Seeder
{
    public function run()
    {
        $valores = [];
        $valores[] = TgEscolaridade::tabela();
        $valores[] = TgSexo::tabela();
        $valores[] = TgContatoTipo::tabela();
        $valores[] = TgDocumentosPessoais::tabela();
        $valores[] = TgTiposAfastamento::tabela();
        $valores[] = TgHistorico::tabela();
        $valores[] = TgMotivo::tabela();
        $valores[] = TgStatus::tabela();
        $valores[] = TgTipoUnidade::tabela();
        $valores[] = TgTipoDependente::tabela();
        $valores[] = TgMotivoFimDependencia::tabela();
        $valores[] = TgEstadoCivil::tabela();
        $valores[] = TgTipoSanguineo::tabela();
        $valores[] = TgFatorRh::tabela();
        $valores[] = TgTipoConselhoClasse::tabela();
        $valores[] = TgTipoContaBancaria::tabela();
        $valores[] = TgTipoPix::tabela();
        $valores[] = TgUnidadePorte::tabela();
        $valores[] = TgTipoEntradaFuncionario::tabela();
        $valores[] = TgTipoSaidaFuncionario::tabela();
        $valores[] = TgTipoAtribuicao::tabela();
        $valores[] = TgAtribuicaoLotacaoCargaHoraria::tabela();
        $valores[] = TgLotacaoTipoFim::tabela();
        $valores[] = TgTipoStatusScala::tabela();
        // Achata o array bidimensional: cada Tg::tabela() retorna um array de rows
        $linhas = array_merge(...$valores);

        // Limpa antes de popular (idempotente)
        DB::table("TABELA_GENERICA")->truncate();
        DB::table("TABELA_GENERICA")->insert($linhas);

    }
}
