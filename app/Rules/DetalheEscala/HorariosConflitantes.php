<?php

namespace App\Rules\DetalheEscala;

use App\Models\DetalheEscalaItem;
use App\Models\Turno;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;

class HorariosConflitantes implements Rule
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
        $request = $this->request;
        foreach ($value as $item) {
            $turno = Turno::find($item['TURNO_ID']);

            $detalhe_escala_item = DetalheEscalaItem::whereDate('DETALHE_ESCALA_ITEM_DATA ', $item['DETALHE_ESCALA_ITEM_DATA'])
                ->whereHas('detalheEscala', function (Builder $query) use ($request) {
                    $query->where('FUNCIONARIO_ID', $request['FUNCIONARIO_ID'])
                        ->where('ESCALA_ID', $request['ESCALA_ID']);
                })
                ->whereHas('turno', function (Builder $query) use ($turno) {
                    $query->whereBetween('TURNO_HORA_INICIO', [$turno->TURNO_HORA_INICIO, $turno->TURNO_HORA_FIM])
                        ->whereBetween('TURNO_HORA_FIM', [$turno->TURNO_HORA_INICIO, $turno->TURNO_HORA_FIM]);
                })
                ->count();

            if ($detalhe_escala_item != 0) return false;
        }
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Possui horarios conflitantes.';
    }
}
