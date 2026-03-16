<?php

namespace App\Rules\LotacaoEvento;

use App\Models\LotacaoEvento;
use Illuminate\Contracts\Validation\Rule;

class ValidarVingencia implements Rule
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
        if (($this->request['LOTACAO_EVENTO_INICIO']) == null)
            return false;
        $periodo = explode('/', $this->request['LOTACAO_EVENTO_INICIO']);
        $inicio =  "$periodo[1]$periodo[0]";
        $lotacaoEvento = LotacaoEvento::where('EVENTO_ID', $this->request['EVENTO_ID'])
            ->where('LOTACAO_ID', $value['LOTACAO_ID'])
            ->whereNull('LOTACAO_EVENTO_FIM')
            ->orWhere('LOTACAO_EVENTO_FIM', '>=', $inicio)
            ->first();
        return $lotacaoEvento ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Não é permitida a existência de um mesmo evento para uma mesma lotação com data fim nula ou vingência com período conflitante.';
    }
}
