<?php

namespace App\Auth\PassportJwtClaims;

use App\Models\LegacyUser;
use CorBosman\Passport\AccessToken;

class AulaClaims
{
    /**
     * Process the generated JWT token, attach aula-specific data to it.
     *
     * @param mixed $next
     */
    public function handle(AccessToken $token, $next)
    {
        $user = LegacyUser::find($token->getUserIdentifier());

        // @TODO: nikola - added to preserve BE.v1 interoperability, should be
        //   removed after FE is fully migrated to BE.v2 API
        $token->addClaim('user_id', $user->id);
        $token->addClaim('user_hash', $user->hash_id);
        $token->addClaim('temp_pw', !empty($user->temp_pw));
        $token->addClaim('user_level', $user->userlevel?->value);
        $token->addClaim('roles', json_decode($user->roles ?? '[]'));

        return $next($token);
    }
}
