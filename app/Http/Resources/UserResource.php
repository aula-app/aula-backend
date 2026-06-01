<?php

namespace App\Http\Resources;

use DateTimeInterface;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
// use App\Models\User;

/**
 * @mixin \App\Domain\Models\User
 */
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
            'realname' => $this->realName,
            'displayname' => $this->displayName,
            'username' => $this->userName,
            'email' => $this->email,
            'hash_id' => $this->hashId,
            'about_me' => $this->aboutMe,

            'created' => $this->createdAt?->format(DateTimeInterface::ISO8601),
            'last_update' => $this->updatedAt?->format(DateTimeInterface::ISO8601),

            'status' => $this->status?->value,
            'userlevel' => $this->userLevel?->value,
            // 'temp_pw' => $this->temp_pw,

        ];
    }
}
