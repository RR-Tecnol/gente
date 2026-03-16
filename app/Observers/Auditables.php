<?php

namespace App\Observers;

use App\Models\AbonoFalta;
use App\Models\Acesso;
use App\Models\Afastamento;
use App\Models\AnexoAbonoFalta;
use App\Models\AnexoAfastamento;
use App\Models\AnexoFerias;
use App\Models\Aplicacao;
use App\Models\Atribuicao;
use App\Models\AtribuicaoConfig;
use App\Models\AtribuicaoLotacao;
use App\Models\AtribuicaoLotacaoEvento;
use App\Models\Bairro;
use App\Models\Banco;
use App\Models\Cartorio;
use App\Models\Certidao;
use App\Models\Cidade;
use App\Models\Conselho;
use App\Models\Contato;
use App\Models\Dependente;
use App\Models\DetalheEscala;
use App\Models\DetalheEscalaAlerta;
use App\Models\DetalheEscalaAutoriza;
use App\Models\DetalheEscalaItem;
use App\Models\DetalheFolha;
use App\Models\Documento;
use App\Models\Escala;
use App\Models\Evento;
use App\Models\EventoDetalheFolha;
use App\Models\EventoVinculo;
use App\Models\Feriado;
use App\Models\Ferias;
use App\Models\Folha;
use App\Models\FolhaSetor;
use App\Models\Funcionario;
use App\Models\HistAtribuicaoConfig;
use App\Models\HistoricoEscala;
use App\Models\HistoricoEvento;
use App\Models\HistoricoFolha;
use App\Models\HistoricoParametro;
use App\Models\Lotacao;
use App\Models\Ocupacao;
use App\Models\ParametroFinanceiro;
use App\Models\Perfil;
use App\Models\Pessoa;
use App\Models\PessoaBanco;
use App\Models\PessoaConselho;
use App\Models\PessoaOcupacao;
use App\Models\Setor;
use App\Models\SetorAtribuicao;
use App\Models\SubstituicaoEscala;
use App\Models\TabelaGenerica;
use App\Models\TabelaImposto;
use App\Models\TipoAlerta;
use App\Models\TipoDocumento;
use App\Models\Tributacao;
use App\Models\Turno;
use App\Models\Uf;
use App\Models\Unidade;
use App\Models\Usuario;
use App\Models\UsuarioPerfil;
use App\Models\UsuarioUnidade;
use App\Models\VigenciaImposto;
use App\Models\Vinculo;
use App\Observers\BaseAuditObserver;
use App\Observers\DocumentoObserver;
use App\Observers\FuncionarioObserver;
use App\Observers\HistoricoEscalaObserver;

class Auditables
{
    public static function register(): void
    {
        // Observadores genéricos
        foreach (self::models() as $model) {
            $model::observe(BaseAuditObserver::class);
        }

        // Observadores específicos
        Documento::observe(DocumentoObserver::class);
        Funcionario::observe(FuncionarioObserver::class);
        HistoricoEscala::observe(HistoricoEscalaObserver::class);
    }

    protected static function models(): array
    {
        return [
            AbonoFalta::class,
            Acesso::class,
            Afastamento::class,
            AnexoAbonoFalta::class,
            AnexoAfastamento::class,
            AnexoFerias::class,
            Aplicacao::class,
            AtribuicaoConfig::class,
            AtribuicaoLotacaoEvento::class,
            AtribuicaoLotacao::class,
            Atribuicao::class,
            Bairro::class,
            Banco::class,
            Cartorio::class,
            Certidao::class,
            Cidade::class,
            Conselho::class,
            Contato::class,
            Dependente::class,
            DetalheEscalaAlerta::class,
            DetalheEscalaAutoriza::class,
            DetalheEscalaItem::class,
            DetalheEscala::class,
            DetalheFolha::class,
            Escala::class,
            EventoDetalheFolha::class,
            Evento::class,
            EventoVinculo::class,
            Feriado::class,
            Ferias::class,
            Folha::class,
            FolhaSetor::class,
            HistAtribuicaoConfig::class,
            HistoricoEvento::class,
            HistoricoFolha::class,
            HistoricoParametro::class,
            Lotacao::class,
            Ocupacao::class,
            ParametroFinanceiro::class,
            Perfil::class,
            PessoaBanco::class,
            PessoaConselho::class,
            Pessoa::class,
            PessoaOcupacao::class,
            SetorAtribuicao::class,
            Setor::class,
            SubstituicaoEscala::class,
            TabelaGenerica::class,
            TabelaImposto::class,
            TipoAlerta::class,
            TipoDocumento::class,
            Tributacao::class,
            Turno::class,
            Uf::class,
            Unidade::class,
            Usuario::class,
            UsuarioPerfil::class,
            UsuarioUnidade::class,
            VigenciaImposto::class,
            Vinculo::class,
        ];
    }
}
