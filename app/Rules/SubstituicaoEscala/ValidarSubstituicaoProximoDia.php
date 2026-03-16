<?php

namespace App\Rules\SubstituicaoEscala;

use App\Models\DetalheEscalaItem;
use Illuminate\Contracts\Validation\Rule;

class ValidarSubstituicaoProximoDia implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
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

        $detalheEscalaItem = DetalheEscalaItem::find($value);
        $data = $detalheEscalaItem->DETALHE_ESCALA_ITEM_DATA;

        //        dd(
        //            [
        //                "DATA_ITEM" => $data,
        //                "DATA_ATUAL"=> date('Y-m-d'),
        //                "DATA_ANTE" => date('Y-m-d', strtotime($data .'-1 day')),
        //                'condicao' => (date('Y-m-d') < date('Y-m-d', strtotime($data .'-1 day')))
        //            ]
        //        );

        return (date('Y-m-d') < date('Y-m-d', strtotime($data . '-1 day')));
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'O prazo de substituição é de até 24 horas.';
    }
}
