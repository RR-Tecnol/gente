<?php

namespace App\Http\Requests\Pessoa;

use Illuminate\Foundation\Http\FormRequest;

class PessoaRegistrarRequest extends FormRequest
{
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "PESSOA_NOME"               => ["required", "max:255"],
            "PESSOA_DATA_NASCIMENTO"    => ["required", "date", "before:18 years ago"],
            "PESSOA_CPF_NUMERO"         => ["required", "cpf", "unique:PESSOA"],
            "PESSOA_EMAIL"              => ['required', 'email'],
        ];
    }

    public function attributes() {
        return [
            "PESSOA_NOME"               => "<b>NOME</b>",
            "PESSOA_CEP"                => "<b>CEP</b>",

            "PESSOA_ENDERECO"           => "<b>ENDEREÇO</b>",
            "BAIRRO_ID"                 => "<b>BAIRRO</b>",
            "CIDADE_ID"                 => "<b>CIDADE</b>",

            "PESSOA_DATA_NASCIMENTO"    => "<b>DATA DE NASCIMENTO</b>",
            "PESSOA_ESCOLARIDADE"       => "<b>ESCOLARIDADE</b>",
            "PESSOA_SEXO"               => "<b>SEXO</b>",
            "PESSOA_NOME_MAE"           => "<b>NOME DA MÃE</b>",
            "CIDADE_ID_NATURAL"         => "<b>NATURALIDADE</b>",
            "PESSOA_NACIONALIDADE"      => "<b>NACIONALIDADE</b>",

            "PESSOA_RACA"                   => "<b>RAÇA</b>",

            "PESSOA_RG_NUMERO"          => "<b>NÚMERO DO RG</b>",
            "PESSOA_RG_EXPEDIDOR"       => "<b>ÓRGÃO EXPEDITOR DO RG</b>",
            "PESSOA_RG_EXPEDICAO"       => "<b>DATA DE EXPEDIÇÃO DO RG</b>",
            "UF_ID_RG"                  => "<b>UF DE EXPEDIÇÃO DO RG</b>",

            "PESSOA_TITULO_NUMERO"      => "<b>NÚMERO DO TÍTULO</b>",
            "PESSOA_TITULO_ZONA"        => "<b>ZONA</b>",
            "PESSOA_TITULO_SECAO"       => "<b>SEÇÃO</b>",
            "UF_ID_TITULO"              => "<b>UF DO TÍTULO</b>",

            "PESSOA_CERTIFICADO_NUMERO"     => "<b>NÚMERO DO CERTIFICADO</b>",
            "PESSOA_CERTIFICADO_SERIE"      => "<b>SÉRIE</b>",
            "PESSOA_CERTIFICADO_CATEGORIA"  => "<b>CATEGORIA DO CERTIFICADO</b>",
            "PESSOA_CERTIFICADO_ORGAO"      => "<b>ÓRGÃO</b>",
            "UF_ID_CERTIFICADO"             => "<b>UF DO CERTIFICADO</b>",

            "PESSOA_CNH_NUMERO"             => "<b>NÚMERO DA CNH</b>",
            "PESSOA_CNH_CATEGORIA"          => "<b>CATEGORIA DA CNH</b>",
            "PESSOA_CNH_VALIDADE"           => "<b>VALIDADE DA CNH</b>",
            "UF_ID_CNH"                     => "<b>UF DA CNH</b>",

            "PESSOA_CPF_NUMERO"             => "<b>NÚMERO DO CPF</b>",
            
            "PESSOA_GENERO"                   => "<b>GÊNERO</b>",
            
            "PESSOA_ESTADO_CIVIL"                   => "<b>ESTADO CIVIL</b>",
            "PESSOA_TIPO_SANGUE"                   => "<b>TIPO SANGUÍNEO</b>",
            "PESSOA_RH_MAIS"                   => "<b>RH</b>",
            "PESSOA_PCD"                   => "<b>PCD</b>",
        ];
    }

    public function messages() {
        return [
            "PESSOA_DATA_NASCIMENTO.before"    => "A pessoa não pode ser <b>MENOR DE 18 ANOS</b>",
            "PESSOA_CERTIFICADO_NUMERO.required_if"    => "Pessoa do sexo <b>MASCULINO</b> sem documento militar",

            "PESSOA_RG_NUMERO.required_with"          => "O campo :attribute é obrigatório",
            "PESSOA_RG_NUMERO.numeric"          => "O campo :attribute deve conter apenas números",
            "PESSOA_RG_EXPEDIDOR.required_with"       => "O campo :attribute é obrigatório",
            "PESSOA_RG_EXPEDICAO.required_with"       => "O campo :attribute é obrigatório",
            "UF_ID_RG.required_with"                  => "O campo :attribute é obrigatório",

            "PESSOA_TITULO_NUMERO.required_with"      => "O campo :attribute é obrigatório",
            "PESSOA_TITULO_ZONA.required_with"        => "O campo :attribute é obrigatório",
            "PESSOA_TITULO_SECAO.required_with"       => "O campo :attribute é obrigatório",
            "UF_ID_TITULO.required_with"              => "O campo :attribute é obrigatório",

            "PESSOA_CERTIFICADO_NUMERO.required_with"     => "O campo :attribute é obrigatório",
            "PESSOA_CERTIFICADO_SERIE.required_with"      => "O campo :attribute é obrigatório",
            "PESSOA_CERTIFICADO_CATEGORIA.required_with"  => "O campo :attribute é obrigatório",
            "PESSOA_CERTIFICADO_ORGAO.required_with"      => "O campo :attribute é obrigatório",
            "UF_ID_CERTIFICADO.required_with"             => "O campo :attribute é obrigatório",

            "PESSOA_CNH_NUMERO.required_with"         => "O campo :attribute é obrigatório",
            "PESSOA_CNH_CATEGORIA.required_with"      => "O campo :attribute é obrigatório",
            "PESSOA_CNH_VALIDADE.required_with"       => "O campo :attribute é obrigatório",
            "UF_ID_CNH.required_with"                 => "O campo :attribute é obrigatório",

            "PESSOA_CPF_NUMERO.required_with"          => "O campo :attribute é obrigatório",
        ];
    }
}
