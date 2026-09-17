<?php

declare(strict_types=1);

namespace App\Auth\PassportJwtClaims;

use App\Models\LegacyUser;
use CorBosman\Passport\AccessToken;
use League\OAuth2\Server\Exception\OAuthServerException;

class AulaClaims
{
    /**
     * Process the generated JWT token, attach aula-specific data to it.
     *
     * @param  mixed  $next
     */
    public function handle(AccessToken $token, $next)
    {
        $user = LegacyUser::find($token->getUserIdentifier());

        // A refresh outlives its user: the row can go between grants.
        if ($user === null) {
            throw OAuthServerException::invalidGrant();
        }

        // legacy/src/controllers/model.php reads user_id unconditionally
        $token->addClaim('user_id', $user->id);
        $token->addClaim('user_hash', $user->hash_id);
        $token->addClaim('temp_pw', ! empty($user->temp_pw));
        $token->addClaim('user_level', $user->userlevel?->value);
        $token->addClaim('roles', json_decode(((string) $user->roles) ?: '[]'));

        return $next($token);
    }
}
