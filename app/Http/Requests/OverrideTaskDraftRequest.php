<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OverrideTaskDraftRequest extends FormRequest
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

            'changes'                    => ['required', 'array', 'min:1'],
            'changes.type'               => ['sometimes', 'nullable', 'string', 'in:bug,feature,question,unclear,other'],
            'changes.title'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'changes.summary'            => ['sometimes', 'nullable', 'string', 'max:5000'],
            'changes.priority'           => ['sometimes', 'nullable', 'string', 'in:low,medium,high,urgent'],
            'changes.suggested_project'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'changes.suggested_team'     => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
