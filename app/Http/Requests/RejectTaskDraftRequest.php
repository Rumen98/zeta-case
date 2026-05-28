<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectTaskDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operator' => ['required', 'string', 'max:255'],
            'reason'   => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }
}
