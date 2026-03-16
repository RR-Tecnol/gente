<?php

namespace App\Rules\AtribuicaoConfig;

use App\Models\AtribuicaoConfig;
use Illuminate\Contracts\Validation\Rule;

class AtrbuicaoConfigUnico implements Rule
{
    private $request;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($request)
    {
        $this->request = $request;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $atribuicaoConfig = AtribuicaoConfig::where('ATRIBUICAO_ID', $value)
            ->where('ATRIBUICAO_CONFIG_CARGA_HORARIA', $this->request['ATRIBUICAO_CONFIG_CARGA_HORARIA'])
            ->where('ATRIBUICAO_CONFIG_PORTE_UNIDADE', $this->request['ATRIBUICAO_CONFIG_PORTE_UNIDADE'])
            ->first();

        return $atribuicaoConfig ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Há mais de um valor informado para a mesma configuração dessa atribuição.';
    }
}
