<?php

namespace App\Http\Controllers;

use App\Http\Requests\Certidao\CertidaoCreateRequest;
use App\Http\Requests\Certidao\CertidaoUpdateRequest;
use App\Models\Certidao;
use App\Models\Pessoa;

class CertidaoController extends Controller
{
    public function create(CertidaoCreateRequest $request)
    {
        $certidao = new Certidao($request->post());
        $certidao->save();
        return response(Pessoa::getById($certidao->PESSOA_ID));
    }

    public function update(CertidaoUpdateRequest $request)
    {
        $certidao = Certidao::find($request->input("CERTIDAO_ID"));
        $certidao->fill($request->input());
        $certidao->update();
        return response(Pessoa::getById($certidao->PESSOA_ID));
    }
}
