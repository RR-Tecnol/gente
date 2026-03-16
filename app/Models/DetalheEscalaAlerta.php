<?php

namespace App\Models;

use App\MyLibs\RelacaoAlertaEscala;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Type\Time;

class DetalheEscalaAlerta extends Model
{
    protected $table = "DETALHE_ESCALA_ALERTA";
    protected $primaryKey = "DETALHE_ESCALA_ALERTA_ID";
    public static $snakeAttributes = false;
    public $timestamps = false;
    protected $fillable = [
        "DETALHE_ESCALA_ID",
        "TIPO_ALERTA_ID",
        "DETALHE_ESCALA_ALERTA_DATA",
        "DETALHE_ESCALA_ALERTA_MENSAGEM",
        "DETALHE_ESCALA_ALERTA_ULTIMO"
    ];

    protected $casts = [
        "DETALHE_ESCALA_ALERTA_ID" => "integer",
        "DETALHE_ESCALA_ID" => "integer",
        "TIPO_ALERTA_ID" => "integer",
        "DETALHE_ESCALA_ALERTA_ULTIMO" => "integer",
    ];

    public function detalheEscala()
    {
        return $this->hasOne(DetalheEscala::class, 'DETALHE_ESCALA_ID', 'DETALHE_ESCALA_ID');
    }

    public function tipoAlerta()
    {
        return $this->hasOne(TipoAlerta::class, 'TIPO_ALERTA_ID', 'TIPO_ALERTA_ID');
    }

    public function inserir($detalheEscala, $dado)
    {
        $detalhe_alerta = new DetalheEscalaAlerta();
        $detalhe_alerta->DETALHE_ESCALA_ID = $detalheEscala->DETALHE_ESCALA_ID;
        $detalhe_alerta->TIPO_ALERTA_ID = $dado['CODIGO'];
        $detalhe_alerta->DETALHE_ESCALA_ALERTA_DATA = date('Y-m-d H:i:s');
        $detalhe_alerta->DETALHE_ESCALA_ALERTA_ULTIMO = 1;
        $detalhe_alerta->DETALHE_ESCALA_ALERTA_MENSAGEM = $dado['MSG'];
        $detalhe_alerta->save();
    }

    public function gerarAlerta($detalheEscala)
    {
        foreach ($detalheEscala->detalheEscalaAlertas as $alerta) {
            $alerta->DETALHE_ESCALA_ALERTA_ULTIMO = 0;
            $alerta->save();
        }

        $this->verificar($detalheEscala);
    }

    public function verificar($detalheEscala)
    {
        $alertas = [];

        $escolaridade = $this->possui_escolaridade($detalheEscala);
        if ($escolaridade) array_push($alertas, $escolaridade);

        $cargaHoraria = $this->conflito_carga_horaria($detalheEscala);
        if ($cargaHoraria) array_push($alertas, $cargaHoraria);

        $conflitoEscala = $this->conflito_escala_datas_turno($detalheEscala);
        if ($conflitoEscala) array_push($alertas, $conflitoEscala);

        foreach ($alertas as $alerta) {
            $this->inserir($detalheEscala, $alerta);
        }
        return $alertas;
    }

    //Rules de alertas
    private function possui_escolaridade($detalheEscala)
    {
        $atribuicao = $detalheEscala->atribuicao;
        $pessoa = $detalheEscala->funcionario->pessoa;
        $escolaridadePessoa = $pessoa->escolaridade->DESCRICAO;
        $escolaridadeAtrib = $atribuicao->atribuicaoEscolaridade->DESCRICAO;

        $msg = "Escolaridade do Funcionário: $escolaridadePessoa | Escolaridade Mínima Exigida: $escolaridadeAtrib";
        return ($pessoa->PESSOA_ESCOLARIDADE < $atribuicao->ATRIBUICAO_ESCOLARIDADE) ? [
            "CODIGO" => RelacaoAlertaEscala::CONFLITO_ESCOLARIDADE,
            "MSG" => $msg,
        ] : false;
    }

    private function conflito_carga_horaria($detalheEscala)
    {
        $lotacao = AtribuicaoLotacao::where('ATRIBUICAO_ID', $detalheEscala->ATRIBUICAO_ID)
            ->whereHas('lotacao', function ($query) use ($detalheEscala) {
                $query->where('SETOR_ID', $detalheEscala->escala->SETOR_ID)
                    ->where('FUNCIONARIO_ID', $detalheEscala->FUNCIONARIO_ID);
            })->first();

        if (!$lotacao) return false;

        [$mes, $ano] = explode('/', $detalheEscala->escala->ESCALA_COMPETENCIA);
        $mesAno = Carbon::createFromDate($ano, $mes, 1);

        // Contagem de dias úteis (segunda a sexta)
        $diasUteis = 0;
        $diasNoMes = $mesAno->daysInMonth;

        for ($dia = 1; $dia <= $diasNoMes; $dia++) {
            $data = Carbon::createFromDate($ano, $mes, $dia);
            if (!$data->isWeekend()) {
                $diasUteis++;
            }
        }

        // Carga horária mensal esperada
        $cargaHorariaDiaria = $lotacao->ATRIBUICAO_LOTACAO_CARGA_HORARIA / 5;
        $cargaHorariaMensal = $cargaHorariaDiaria * $diasUteis;

        // Cálculo da carga horária escalada real
        $cargaHorariaEscala = 0;

        foreach ($detalheEscala->detalheEscalaItens as $item) {
            $horaInicio = intval(substr($item->turno->TURNO_HORA_INICIO, 0, 2));
            $horaFim = intval(substr($item->turno->TURNO_HORA_FIM, 0, 2));
            $intervalo = intval($item->turno->TURNO_INTERVALO ?? 0);

            $horasDoTurno = ($horaFim - $horaInicio) - $intervalo;

            $cargaHorariaEscala += $horasDoTurno;
        }

        // Mensagem informativa
        $msg = "Carga Horária Mensal dessa escala foi de: {$cargaHorariaEscala} h | Carga Horária Mensal esperada: {$cargaHorariaMensal} h (com base em {$diasUteis} dias úteis)";

        if ($cargaHorariaEscala > $cargaHorariaMensal) {
            return [
                "CODIGO" => RelacaoAlertaEscala::CONFLITO_CARGA_HORARIA_ULTRAPASSADA,
                "MSG" => $msg,
            ];
        } elseif ($cargaHorariaEscala < $cargaHorariaMensal) {
            return [
                "CODIGO" => RelacaoAlertaEscala::CONFLITO_CARGA_HORARIA_ABAIXO,
                "MSG" => $msg,
            ];
        } else {
            return false;
        }
    }

    private function conflito_escala($detalheEscala)
    {
        $detalheEscalas = DetalheEscala::whereKeyNot($detalheEscala->DETALHE_ESCALA_ID)
            ->whereHas('escala', function (Builder $query) use ($detalheEscala) {
                $query->where('ESCALA_COMPETENCIA', $this->formataPeriodo($detalheEscala->escala->ESCALA_COMPETENCIA))
                    ->whereKeyNot($detalheEscala->ESCALA_ID);
            })
            ->where('FUNCIONARIO_ID', $detalheEscala->FUNCIONARIO_ID)
            ->get();

        return (count($detalheEscalas) > 0) ? [
            "CODIGO" => RelacaoAlertaEscala::CONFLITO_ESCALA,
            "MSG" => "Funcionario encontra-se em outra escala do mesmo periodo. ",
        ] : false;
    }

    private function conflito_escala_datas_turno($detalheEscala)
    {
        $erros = [];
        $detalheEscalas = DetalheEscala::whereKeyNot($detalheEscala->DETALHE_ESCALA_ID)
            ->whereHas('escala', function (Builder $query) use ($detalheEscala) {
                $query->where('ESCALA_COMPETENCIA', $this->formataPeriodo($detalheEscala->escala->ESCALA_COMPETENCIA))
                    ->whereKeyNot($detalheEscala->ESCALA_ID);
            })
            ->where('FUNCIONARIO_ID', $detalheEscala->FUNCIONARIO_ID)
            ->get();
        foreach ($detalheEscalas as $detalhe) {
            foreach ($detalhe->detalheEscalaItens as $item) {
                foreach ($detalheEscala->detalheEscalaItens as $nvItem) {
                    if ($item->DETALHE_ESCALA_ITEM_DATA == $nvItem->DETALHE_ESCALA_ITEM_DATA && $item->TURNO_ID == $nvItem->TURNO_ID) {
                        array_push($erros, $nvItem);
                    }
                }
            }
        }
        $msg = "Conflitos em: <br/>";
        foreach ($erros as $erro) {
            $dia = date('d/m/Y', strtotime($erro->DETALHE_ESCALA_ITEM_DATA));
            $turno = $erro->turno->TURNO_DESCRICAO;
            $setor = $erro->detalheEscala->escala->setor->SETOR_NOME;
            $msg .= " No DIA: $dia. No TURNO: $turno. No SETOR: $setor. <br/>";
        }
        return (count($detalheEscalas) > 0) ? [
            "CODIGO" => RelacaoAlertaEscala::CONFLITO_ESCALA,
            "MSG" => $msg,
        ] : false;
    }

    // public function possui_horario_igual(){
    //     $retorno = DB::select(
    //     DB::raw("
    //     select
    //     DE.DETALHE_ESCALA_DATA,
    //     MAX(DE.DETALHE_ESCALA_ID) DETALHE_ESCALA_ID,
    //     1 TIPO_ALERTA_ID
    //     from DETALHE_ESCALA DE
    //     JOIN ESCALA E ON E.ESCALA_ID = DE.ESCALA_ID
    //     WHERE DE.DETALHE_ESCALA_ATIVO = 1
    //     group by DE.FUNCIONARIO_ID,DE.DETALHE_ESCALA_DATA,DE.TURNO_ID
    //     HAVING COUNT(*) > 1

    //     UNION

    //     select
    //     DE.DETALHE_ESCALA_DATA,
    //     MIN(DE.DETALHE_ESCALA_ID) DETALHE_ESCALA_ID,
    //     1 TIPO_ALERTA_ID
    //     from DETALHE_ESCALA DE
    //     JOIN ESCALA E ON E.ESCALA_ID = DE.ESCALA_ID
    //     WHERE DE.DETALHE_ESCALA_ATIVO = 1
    //     group by DE.FUNCIONARIO_ID,DE.DETALHE_ESCALA_DATA,DE.TURNO_ID
    //     HAVING COUNT(*) > 1
    //     ")
    //     );

    //     return $retorno;
    // }

    // public function possui_escolaridade1($id){
    //     $retorno = DB::select(
    //     DB::raw("
    //     SELECT
    //     *
    //     FROM
    //     (SELECT
    //     DE.DETALHE_ESCALA_ID,
    //     3 TIPO_ALERTA_ID
    //     FROM DETALHE_ESCALA DE
    //     JOIN FUNCIONARIO F ON F.FUNCIONARIO_ID = DE.FUNCIONARIO_ID
    //     JOIN PESSOA P  ON P.PESSOA_ID = F.PESSOA_ID
    //     JOIN CARGO C ON C.CARGO_ID = DE.CARGO_ID
    //     WHERE P.PESSOA_ESCOLARIDADE > C.CARGO_ESCOLARIDADE) AS POSSUI
    //     WHERE DETALHE_ESCALA_ID = $id;
    //     ")
    //     );

    //     return $retorno;
    // }

    private function formataPeriodo($competencia)
    {
        $periodo = explode('/', $competencia);
        return "$periodo[1]$periodo[0]";
    }
}
