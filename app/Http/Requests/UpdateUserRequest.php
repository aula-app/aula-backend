<?php

namespace App\Http\Requests;

use App\Enums\UserLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // TODO
        return true;
    }

    public function rules(): array
    {
        // TODO: not DRY, see storeuserrequest
        return [
            'displayname' => ['required', 'string', 'max:400'],
            'username' => ['required', 'string', 'max:400'],
            'realname' => ['required', 'string', 'max:400'],
            'email' => ['required', 'email:strict,spoof'],
            'userlevel' => [Rule::enum(UserLevel::class)],
            'status' => ['int:strict', 'between:0,4'],
        ];
    }
}

