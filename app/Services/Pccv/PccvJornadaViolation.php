<?php

namespace App\Services\Pccv;

/**
 * Infração à regra de jornada (Lei 4.928/2008 — alinhamento operacional Frente 5).
 */
final class PccvJornadaViolation
{
    /** @var int */
    public $cargaHorariaSemanalContrato;

    /** @var float */
    public $horasAgendadasSemana;

    /** @var string */
    public $inicioSemana;

    /** @var string */
    public $fimSemana;

    /** @var string */
    public $message;

    public function __construct(
        $cargaHorariaSemanalContrato,
        $horasAgendadasSemana,
        $inicioSemana,
        $fimSemana,
        $message = ''
    ) {
        $this->cargaHorariaSemanalContrato = (int) $cargaHorariaSemanalContrato;
        $this->horasAgendadasSemana = (float) $horasAgendadasSemana;
        $this->inicioSemana = (string) $inicioSemana;
        $this->fimSemana = (string) $fimSemana;
        $this->message = (string) $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        $msg = $this->message !== '' ? $this->message
            : 'A soma das horas de turno na semana excede a carga horária contratual (PCCV / base legal municipal).';

        return [
            'code' => 'PCCV_JORNADA_EXCEDIDA',
            'carga_semanal_contrato' => $this->cargaHorariaSemanalContrato,
            'horas_agendadas_na_semana' => $this->horasAgendadasSemana,
            'inicio_semana' => $this->inicioSemana,
            'fim_semana' => $this->fimSemana,
            'mensagem' => $msg,
        ];
    }
}
