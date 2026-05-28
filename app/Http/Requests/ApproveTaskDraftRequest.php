<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveTaskDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operator' => ['required', 'string', 'max:255'],
            'note'     => ['nullable', 'string', 'max:5000'],
        ];
    }
}
