<?php

use Illuminate\Support\Facades\DB;

/**
 * OSS — Monitor de Organizações Sociais de Saúde
 * ADMIN-ONLY. Dados de contrato reais + indicadores mock para PoC.
 * GAP-OSS: indicadores reais serão integrados pós-contrato.
 */

// Listar OSS com dados de contrato reais
Route::get('/oss', function () {
    try {
        $empresas = DB::table('TERCEIRO_EMPRESA')
            ->where('STATUS', 'ATIVO')
            ->select('EMPRESA_ID', 'RAZAO_SOCIAL', 'CNPJ', 'CONTRATO_NUM',
                     'VIGENCIA_INICIO', 'VIGENCIA_FIM', 'VALOR_MENSAL')
            ->get();

        return response()->json([
            'oss'   => $empresas,
            'total' => $empresas->count(),
            'aviso' => 'Indicadores qualitativos em fase de integração — exibindo dados de referência.',
        ]);
    } catch (\Throwable $e) {
        return response()->json(['oss' => [], 'erro' => $e->getMessage()], 500);
    }
});

// Indicadores de uma OSS — stub para PoC
Route::get('/oss/{id}/indicadores', function (int $id) {
    try {
        $empresa = DB::table('TERCEIRO_EMPRESA')->where('EMPRESA_ID', $id)->first();
        if (!$empresa) return response()->json(['erro' => 'OSS não encontrada.'], 404);

        // Stub de indicadores — serão substituídos por dados reais pós-contrato
        return response()->json([
            'empresa_id'  => $id,
            'razao_social'=> $empresa->RAZAO_SOCIAL,
            'competencia' => request('competencia', date('m/Y')),
            'mock'        => true,
            'indicadores' => [
                ['codigo' => 'IND-01', 'nome' => 'Taxa de Ocupação de Leitos',      'meta' => 85, 'realizado' => 88, 'unidade' => '%'],
                ['codigo' => 'IND-02', 'nome' => 'Tempo Médio de Espera (Triagem)', 'meta' => 30, 'realizado' => 24, 'unidade' => 'min'],
                ['codigo' => 'IND-03', 'nome' => 'Consultas Realizadas',             'meta' => 2000, 'realizado' => 1847, 'unidade' => 'atend.'],
                ['codigo' => 'IND-04', 'nome' => 'Satisfação do Usuário',            'meta' => 80, 'realizado' => 76, 'unidade' => '%'],
                ['codigo' => 'IND-05', 'nome' => 'Regularidade Trabalhista',         'meta' => 100, 'realizado' => 100, 'unidade' => '%'],
                ['codigo' => 'IND-06', 'nome' => 'Exames Realizados',                'meta' => 1500, 'realizado' => 1612, 'unidade' => 'exames'],
                ['codigo' => 'IND-07', 'nome' => 'Taxa de Infecção Hospitalar',      'meta' => 2, 'realizado' => 1.8, 'unidade' => '%', 'inverso' => true],
                ['codigo' => 'IND-08', 'nome' => 'Cumprimento do Plano Operativo',   'meta' => 100, 'realizado' => 92, 'unidade' => '%'],
            ],
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
