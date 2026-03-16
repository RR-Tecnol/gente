<?php

namespace App\Http\Requests\Folha;

use Illuminate\Foundation\Http\FormRequest;

class FolhaUpdateRequest extends FolhaCreateRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'FOLHA_ID' => ['required']
        ];
    }
}
