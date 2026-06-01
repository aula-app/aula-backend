<?php

namespace App\Http\Requests;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // TODO
        return true;
    }

    /**
     * Validation rules
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // TODO alpha_num instead of string?
            'displayname' => ['required', 'string', 'max:400'],
            'username' => ['required', 'string', 'max:400'],
            'realname' => ['required', 'string', 'max:400'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            // spoof requires php-intl
            'email' => ['email:strict,spoof'],
            'userlevel' => [Rule::enum(UserLevel::class)],
        ];
    }
}
