<?php

/**
 * Padrões SEMAD / São Luís (S3) — sobrescrevíveis via tabela JORNADA_REGRA_PARAM.
 */
return [

    /** Tolerância de batida de ponto (minutos) — apuração. */
    'ponto_tolerancia_minutos' => (int) env('JORNADA_PONTO_TOLERANCIA_MIN', 15),

    /**
     * Teto de duração de um acionamento de sobreaviso (horas) — regra 24h (BRAIN).
     */
    'sobreaviso_acionamento_teto_horas' => (float) env('JORNADA_SOBREAVISO_TETO_H', 24),

    /**
     * Fração do valor da hora normal paga como adicional de sobreaviso/acionamento (1/3 ≈ 0,333...).
     */
    'sobreaviso_adicional_fracao_hora_normal' => (float) env('JORNADA_SOBREAVISO_FRACAO', 1 / 3),

    /**
     * Valor-hora de referência (R$) usado em acionamento até existir rubrica no vínculo.
     * Alinhar depois a folha / ACT / convenção.
     */
    'valor_hora_referencia_rs' => (float) env('JORNADA_VALOR_HORA_REF', 74.0),
];
