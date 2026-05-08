<?php

return [
    'versao' => '2026-04-27',
    'fontes' => [
        [
            'id' => 'dossie_terceirizacao',
            'endpoint' => '/api/v3/transparencia/dossie-terceirizacao',
            'descricao' => 'Relação pública mínima de postos terceirizados ativos',
            'atualizacao' => 'diaria',
            'base_legal' => 'LC 131/2009; Decreto 7.185/2010; política LGPD institucional',
            'campos_publicos' => ['nome', 'funcao', 'empresa', 'contrato', 'secretaria', 'setor', 'cpf_mascarado'],
            'restricoes' => ['sem CPF integral', 'sem remuneração individual nominal'],
        ],
        [
            'id' => 'observabilidade_integracoes',
            'endpoint' => '/api/v3/transparencia/observabilidade-integracoes',
            'descricao' => 'Indicadores agregados de operação (transparência/eSocial/RPPS)',
            'atualizacao' => 'quase_tempo_real',
            'base_legal' => 'Governança de dados e transparência ativa',
            'campos_publicos' => ['gerado_em', 'metricas'],
            'restricoes' => ['dados agregados', 'sem identificação pessoal'],
        ],
    ],
];
