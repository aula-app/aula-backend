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
            // TODO: max/min size, pattern?
            'displayname' => ['required','string','max:400'],
            'username' => ['required'],
            'realname' => ['required'],
            'userlevel' => [Rule::enum(UserLevel::class)],
        ];
    }
}
