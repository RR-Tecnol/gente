<?php

namespace App\Http\Controllers;

use App\Http\Requests\AtribuicaoConfig\AtribuicaoConfigCreateRequest;
use App\Http\Requests\AtribuicaoConfig\AtribuicaoConfigUpdateRequest;
use App\Models\Atribuicao;
use App\Models\AtribuicaoConfig;
use App\Models\HistAtribuicaoConfig;
use Illuminate\Support\Facades\DB;

class AtribuicaoConfigController extends Controller
{
    public function create(AtribuicaoConfigCreateRequest $request)
    {
        DB::beginTransaction();
        $atribuicaoConfig = new AtribuicaoConfig($request->input());
        $atribuicaoConfig->save();

        HistAtribuicaoConfig::addHistoricoConfig($atribuicaoConfig, $request->HIST_ATRIBUICAO_CONFIG_VALOR, $request->HIST_ATRIBUICAO_CONFIG_EXTRA);
        DB::commit();

        return response(Atribuicao::buscar($atribuicaoConfig->ATRIBUICAO_ID), 200);
    }

    public function update(AtribuicaoConfigUpdateRequest $request)
    {
        $atribuicaoConfig = AtribuicaoConfig::find($request->ATRIBUICAO_CONFIG_ID);

        HistAtribuicaoConfig::addHistoricoConfig($atribuicaoConfig, $request->HIST_ATRIBUICAO_CONFIG_VALOR, $request->HIST_ATRIBUICAO_CONFIG_EXTRA);

        return response(Atribuicao::buscar($atribuicaoConfig->ATRIBUICAO_ID), 200);
    }
}
