<?php

namespace App\Rules;

use App\Models\DetalheEscalaItem;
use Illuminate\Contracts\Validation\Rule;

class DiaTurnoUnico implements Rule
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
        $detalheEscalaItem = DetalheEscalaItem::where($attribute, $value)
            ->where('DETALHE_ESCALA_ITEM_DATA', $this->request['DETALHE_ESCALA_ITEM_DATA'])
            ->where('TURNO_ID', $this->request['TURNO_ID'])
            ->first();

        return $detalheEscalaItem ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Já turno e data já existente.';
    }
}
