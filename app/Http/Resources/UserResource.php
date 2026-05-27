<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'roles' => $this->roles,
            'realname' => $this->realname,
            'displayname' => $this->displayname,
            'username' => $this->username,
            'email' => $this->email,
            'hash_id' => $this->hash_id,
            'about_me' => $this->about_me,
            'status' => $this->status,
            'registration_status' => $this->registration_status,
            'created' => $this->created,
            'last_update' => $this->last_update,
            'userlevel' => $this->userlevel?->value,
            'temp_pw' => $this->temp_pw,
        ];
    }
}
