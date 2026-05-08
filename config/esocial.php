<?php

return [
    /*
    |--------------------------------------------------------------------------
    | eSocial Queue Resilience (P2/P5-standby)
    |--------------------------------------------------------------------------
    */
    'max_retry' => (int) env('ESOCIAL_MAX_RETRY', 5),

    /*
    |--------------------------------------------------------------------------
    | Empregador (CNPJ raiz da prefeitura)
    |--------------------------------------------------------------------------
    | Substitui hardcode em EsocialXmlService (R59).
    | Para multi-município (P6), passar via tenant context — CNPJ aqui é fallback.
    */
    'cnpj_empregador' => env('ESOCIAL_CNPJ_EMPREGADOR', '06205244000149'), // PMSL/MA default
    'tipo_inscricao' => env('ESOCIAL_TIPO_INSCRICAO', '1'), // 1=CNPJ, 2=CPF

    /*
    |--------------------------------------------------------------------------
    | Ambiente do eSocial
    |--------------------------------------------------------------------------
    | 1 = Produção (vai para o governo de verdade)
    | 2 = Produção restrita (homologação oficial do governo)
    |
    | DEFAULT é 2 (homologação). Para enviar pra produção real, configurar
    | ESOCIAL_AMBIENTE=1 no .env DA PRODUÇÃO. Em dev/staging, sempre 2.
    | Substitui hardcode em EsocialXmlService (R53).
    */
    'ambiente' => (int) env('ESOCIAL_AMBIENTE', 2),

    /*
    |--------------------------------------------------------------------------
    | Versão do processador emissor (verProc)
    |--------------------------------------------------------------------------
    */
    'versao_proc' => env('ESOCIAL_VERSAO_PROC', 'GENTE-v3'),

    /*
    |--------------------------------------------------------------------------
    | Mapeamento de domínios PESSOA → códigos eSocial
    |--------------------------------------------------------------------------
    | Os campos `PESSOA_GENERO`, `PESSOA_RACA`, `PESSOA_ESTADO_CIVIL`,
    | `PESSOA_ESCOLARIDADE` em GENTE são integers que apontam para TABELA_GENERICA.
    | O eSocial usa códigos próprios. Este mapeamento traduz.
    |
    | Documentação eSocial: Tabela 02 (raça/cor), Tabela 17 (estado civil),
    | Tabela 18 (grau instrução).
    */
    'mapeamento' => [
        // PESSOA_GENERO → eSocial sexo (M/F)
        'sexo' => [
            1 => 'M', // Masculino
            2 => 'F', // Feminino
        ],
        // PESSOA_RACA → eSocial racaCor (1-6 + 9)
        'raca_cor' => [
            1 => '1', // Branca
            2 => '2', // Preta
            3 => '3', // Parda
            4 => '4', // Amarela
            5 => '5', // Indígena
            6 => '6', // Não informado
        ],
        // PESSOA_ESTADO_CIVIL → eSocial estCiv (1-5)
        'estado_civil' => [
            1 => '1', // Solteiro
            2 => '2', // Casado
            3 => '3', // Divorciado
            4 => '4', // Separado
            5 => '5', // Viúvo
        ],
        // PESSOA_ESCOLARIDADE → eSocial grauInstr (01-12)
        'grau_instrucao' => [
            1 => '01',  // Analfabeto
            2 => '02',  // Até 5ª incompleto
            3 => '03',  // 5ª completo fundamental
            4 => '04',  // 6ª a 9ª fundamental
            5 => '05',  // Fundamental completo
            6 => '06',  // Médio incompleto
            7 => '07',  // Médio completo
            8 => '08',  // Superior incompleto
            9 => '09',  // Superior completo
            10 => '10', // Pós-graduação
            11 => '11', // Mestrado
            12 => '12', // Doutorado
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Indicador de retificação default
    |--------------------------------------------------------------------------
    | 1 = Original (primeiro envio)
    | 2 = Retificação (correção de evento já enviado — exige campo nrRecibo)
    | 3 = Exclusão de evento já enviado
    | Substitui hardcode em EsocialXmlService (R60).
    */
    'ind_retif_default' => (int) env('ESOCIAL_IND_RETIF_DEFAULT', 1),
];
