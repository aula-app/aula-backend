<?php

namespace App\Http\Requests;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
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
        // TODO: not DRY, see StoreUserRequest, but differs in what is required
        return [
            'displayname' => ['required', 'string', 'max:400'],
            'username' => ['required', 'string', 'max:400'],
            'realname' => ['required', 'string', 'max:400'],
            'status' => [Rule::enum(UserStatus::class)],
            'email' => ['email:strict,spoof'],
            'userlevel' => [Rule::enum(UserLevel::class)],
        ];
    }
}

