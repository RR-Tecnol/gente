<?php

namespace App\Http\Controllers;

use App\Helpers\DadosFormatter;
use App\Http\Requests\Relatorio\HomologacaoUnidadeRequest;
use App\Models\Escala;
use App\Models\Lotacao;
use App\Models\Unidade;
use Eltonwebnet\JasperRdr\JasperRdr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Jaspersoft\Client\Client;

class RelatorioController extends Controller
{

    public function imprimirEscala($id)
    {
        $escala = Escala::find($id);
        $unidade = Str::slug($escala->setor->unidade->UNIDADE_NOME, '-');
        $setor = Str::slug($escala->setor->SETOR_NOME, '-');

        $periodo = explode('/', $escala->ESCALA_COMPETENCIA);
        $mes = $periodo[0];
        $ano = $periodo[1];

        $parametros = [
            'p_escala' => $escala->ESCALA_ID,
            'p_mes' => $mes,
            'p_ano' => $ano
        ];

        $relatorio = [
            'url' => "/reports/SISFOLHA/" . env('JASPER_DATA_SOURCE') . "/EscalaDeTrabalho",
            'formatoSaida' => 'pdf'
        ];

        $client = new Client(
            env('JASPER_HOST'),
            env('JASPER_USER'),
            env('JASPER_PASSWORD')
        );

        $report = $client->reportService()->runReport($relatorio["url"], $relatorio["formatoSaida"], null, null, $parametros);

        $nomeArquivo = $unidade . '_' . $setor . '_' . $periodo[0] . '_' . $periodo[1];

        $headers = [
            'Content-Type' => 'application/pdf',
            'filename=$nomeArquivo.pdf',
        ];

        Storage::put("public/{$relatorio['formatoSaida']}", $report);

        return response()->download(
            storage_path("app/public/{$relatorio['formatoSaida']}"),
            "$nomeArquivo.{$relatorio['formatoSaida']}",
            $headers
        );
    }

    public function imprimirLotacao($id)
    {
        $lotacao = Lotacao::find($id);
        $pessoa = Str::slug($lotacao->funcionario->pessoa->PESSOA_NOME, '-');

        $headers = [
            'Content-Type' => 'application/pdf',
        ];

        $template = resource_path("reports/builds/TermoEncaminhamentoLotacao.jasper");

        $dados = Lotacao::getDadosRelatorioImprimirLotacao($id);

        foreach ($dados as $dado) {
            $dado->LOTACAO_DATA_INICIO = DadosFormatter::formatarData($dado->LOTACAO_DATA_INICIO);
            $dado->CPF = DadosFormatter::formatarCpf($dado->CPF);
            $dado->CELULAR = DadosFormatter::formatarCelular($dado->CELULAR);
            $dado->TEL = DadosFormatter::formatarCelular($dado->TEL);

            $campos = ['PESSOA_ENDERECO', 'PESSOA_COMPLEMENTO', 'BAIRRO_NOME', 'CIDADE_NOME'];

            foreach ($campos as $campo) {
                $dado->$campo = DadosFormatter::toUpper($dado->$campo);
            }
        }

        $parameters = [
            "pBrasao" => resource_path("reports/images/brasao.png"),
            "pLotacaoId" => $id,
        ];

        $tipo = "pdf";
        $nomeArquivo = $pessoa . "_" . date("d-m-Y");
        $result = JasperRdr::render(json_encode($dados), $template, $parameters, $tipo);

        return response()
            ->download(
                $result["report"],
                "$nomeArquivo." . $result["extension"],
                $headers,
            )->deleteFileAfterSend(true);
    }

    public function imprimirUnidade(HomologacaoUnidadeRequest $request)
    {
        $unidade = Unidade::find($request->pUnidadeId);
        $unidadeNome = Str::slug($unidade->UNIDADE_NOME, '-');

        $headers = [
            'Content-Type' => 'application/pdf',
        ];

        $template = resource_path("reports/builds/HomologacaoDeUnidade.jasper");

        $dados = Unidade::getDadosRelatorioImprimirUnidade($request->pUnidadeId, $request->pSetorId, $request->pAtribuicaoId);

        $parameters = [
            "pBrasao" => resource_path("reports/images/brasao.png"),
            "pUnidadeId" => $request->pUnidadeId,
            "pSetorId" => $request->pSetorId,
            "pAtribuicaoId" => $request->pAtribuicaoId,
        ];

        $tipo = "pdf";
        $nomeArquivo = $unidadeNome . "_" . date("d-m-Y");
        $result = JasperRdr::render(json_encode($dados), $template, $parameters, $tipo);

        return response()
            ->download(
                $result["report"],
                "$nomeArquivo." . $result["extension"],
                $headers,
            )->deleteFileAfterSend(true);
    }

    public function homologacaoUnidadeView()
    {
        return view('relatorio.homologacao_unidade_view')->with([
            'unidades' => Unidade::where('UNIDADE_ATIVA', 1)->orderBy('UNIDADE_NOME')->get(),
        ]);
    }
}
