<?php

namespace App\Http\Requests\Certidao;

use App\MyLibs\CertidaoTipoEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CertidaoCreateRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "CERTIDAO_TIPO"     => [
                "required",
                Rule::unique("CERTIDAO")->where(function (Builder $q) {
                    $q
                        ->where("PESSOA_ID", $this->input("PESSOA_ID"))
                        ->when($this->input("CERTIDAO_TIPO"), function (Builder $q) {
                            $q->where("CERTIDAO_TIPO", $this->input("CERTIDAO_TIPO"))
                                ->where("CERTIDAO_TIPO", "=", CertidaoTipoEnum::NASCIMENTO)
                                ->orWhere("CERTIDAO_TIPO", "=", CertidaoTipoEnum::OBITO);
                        });
                })
            ],
            "CARTORIO_ID"       => ["required"],
            "CERTIDAO_MATRICULA"=> ["required", "max:50"],
            "CERTIDAO_NUMERO"   => ["required", "numeric"],
            "CERTIDAO_LIVRO"    => ["nullable", "numeric"],
            "CERTIDAO_FOLHA"    => ["nullable", "numeric"],
            "PESSOA_ID"         => ["required"],
        ];
    }

    public function attributes() {
        return [
            "CERTIDAO_TIPO"     => "<b>TIPO DE CERTIDÃO</b>",
            "CERTIDAO_MATRICULA"=> "<b>MATRÍCULA</b>",
            "CERTIDAO_NUMERO"   => "<b>NÚMERO DA CERTIDÃO</b>",
            "CERTIDAO_LIVRO"    => "<b>LIVRO</b>",
            "CERTIDAO_FOLHA"    => "<b>FOLHA</b>",
            "CARTORIO_ID"       => "<b>CARTÓRIO</b>",
            "PESSOA_ID"         => "<b>PESSOA</b>",
        ];
    }

    public function messages() {
        return [
            "CERTIDAO_TIPO.unique" => "O <b>TIPO DE CERTIDÃO</b> já foi cadastrado"
        ];
    }
}
