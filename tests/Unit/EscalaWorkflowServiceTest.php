<?php

namespace Tests\Unit;

use App\Domain\Escala\EscalaWorkflowService;
use App\Domain\Escala\EscalaWorkflowStatus;
use PHPUnit\Framework\TestCase;

class EscalaWorkflowServiceTest extends TestCase
{
    public function test_resolver_transicao_enviar_de_rascunho(): void
    {
        $t = EscalaWorkflowService::resolverTransicao(
            EscalaWorkflowStatus::RASCUNHO,
            EscalaWorkflowService::ACAO_ENVIAR
        );
        $this->assertSame(EscalaWorkflowStatus::RASCUNHO, $t['from']);
        $this->assertSame(EscalaWorkflowStatus::EM_VALIDACAO_SUPERINTENDENCIA, $t['to']);
    }

    public function test_resolver_transicao_homologar_de_em_validacao(): void
    {
        $t = EscalaWorkflowService::resolverTransicao(
            EscalaWorkflowStatus::EM_VALIDACAO_SUPERINTENDENCIA,
            EscalaWorkflowService::ACAO_HOMOLOGAR
        );
        $this->assertSame(EscalaWorkflowStatus::HOMOLOGADO_SAGEP, $t['to']);
    }

    public function test_assert_pode_editar_grade_em_devolvida(): void
    {
        EscalaWorkflowService::assertPodeEditarGrade([
            'ESCALA_STATUS' => EscalaWorkflowStatus::DEVOLVIDA_PARA_AJUSTE,
        ], null);
        $this->assertTrue(true);
    }

    public function test_assert_pode_editar_grade_bloqueia_em_validacao_sem_request(): void
    {
        $this->expectException(\RuntimeException::class);
        EscalaWorkflowService::assertPodeEditarGrade([
            'ESCALA_STATUS' => EscalaWorkflowStatus::EM_VALIDACAO_SUPERINTENDENCIA,
        ], null);
    }

    public function test_normalizar_status_aberta_para_rascunho(): void
    {
        $this->assertSame(
            EscalaWorkflowStatus::RASCUNHO,
            EscalaWorkflowService::normalizarStatusLeitura('Aberta')
        );
    }

    public function test_normalizar_status_fechada_legado_para_rascunho(): void
    {
        $this->assertSame(
            EscalaWorkflowStatus::RASCUNHO,
            EscalaWorkflowService::normalizarStatusLeitura('Fechada')
        );
    }
}
