<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // TODO
        return true;
    }

    public function rules(): array
    {
        return [
            'displayname' => ['required'],
            'username' => ['required'],
            'realname' => ['required'],
        ];
    }
}

