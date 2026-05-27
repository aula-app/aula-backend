<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // TODO
        return true;
    }

    public function rules(): array
    {
        // TODO other properties
        return [
            'displayname' => ['required'],
            'username' => ['required'],
            'realname' => ['required'],
        ];
    }
}
