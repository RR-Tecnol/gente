<?php

namespace App\Rules\SubstituicaoEscala;

use App\Models\DetalheEscalaItem;
use App\Models\Funcionario;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;

class ValidarSubstituicaoAtribuicao implements Rule
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

        $detalheEscalaItem = DetalheEscalaItem::find($this->request['DETALHE_ESCALA_ITEM_ID']);
        $cargo = $detalheEscalaItem->detalheEscala->ATRIBUICAO_ID;

        $funcionario = Funcionario::where('FUNCIONARIO_ID', $value)
            ->whereHas('lotacoes.atribuicaoLotacoes', function (Builder $query) use ($cargo) {
                $query->where('ATRIBUICAO_ID', $cargo);
            })
            ->first();

        // dd([
        //     $setor,
        //     $funcionario,
        //     Funcionario::with(['lotacoes'])->find($value),
        // ]);
        return $funcionario ? true : false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'O funcionario precisa ter a mesma atribuição do substituto.';
    }
}
