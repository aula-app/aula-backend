<?php

namespace App\Http\Requests;

use App\Enums\UserLevel;
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
        // TODO other properties
        /* 'username' => [ */
        /*     'required', */
        /*     Rule::anyOf([ */
        /*         ['string', 'email'], */
        /*         ['string', 'alpha_dash', 'min:6'], */
        /*     ]), */
        /* ], */
        return [
            // TODO alpha_num instead of string?
            'displayname' => ['required', 'string', 'max:400'],
            'username' => ['required', 'string', 'max:400'],
            'realname' => ['required', 'string', 'max:400'],
            // spoof requires php-intl
            'email' => ['required', 'email:strict,spoof'],
            // optional
            'userlevel' => [Rule::enum(UserLevel::class)],
            'status' => ['int:strict', 'between:0,4'],
        ];
    }
}
