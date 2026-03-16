<?php

namespace App\Http\Requests\Pessoa;

use App\Rules\Contato\ChecarContatoFilho;
use App\Rules\Documento\ChecarDocumentoFilho;
use App\Rules\Funcionario\ChecarPessoaFuncionario;
use App\Rules\PessoaProfissao\ChecarPessoaProfissaoFilho;

class PessoaDeleteRequest extends PessoaCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'id' => [ 'required', 'int', new ChecarPessoaProfissaoFilho, new ChecarContatoFilho, new ChecarDocumentoFilho, new ChecarPessoaFuncionario ]
        ];
    }
}
