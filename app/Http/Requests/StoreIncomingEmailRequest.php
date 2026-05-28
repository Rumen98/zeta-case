<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncomingEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from'    => ['required', 'email:rfc', 'max:255'],
            'subject' => ['required', 'string', 'min:1', 'max:998'],
            'body'    => ['required', 'string', 'min:10', 'max:65535'],
        ];
    }
}
