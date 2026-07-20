<?php

declare(strict_types=1);

use App\Services\Idp\Providers\Eduplaces\EduplacesDirectory;
use App\Services\Idp\Providers\Eduplaces\EduplacesWebhookAdapter;

return [

    /*
    |--------------------------------------------------------------------------
    | Identity provider directories
    |--------------------------------------------------------------------------
    |
    | Each entry describes one upstream identity provider aula can sync a school
    | from. A tenant selects one by `tenants.sso_provider`, which is also the
    | Keycloak IdP alias, so a tenant's login and its directory always agree.
    |
    | Adding a provider is a block here plus two classes — an IdentityDirectory
    | and a WebhookAdapter. No migrations, no routes, no changes to the import,
    | the resolver or the syncs.
    |
    */

    'providers' => [

        'eduplaces' => [
            'directory' => EduplacesDirectory::class,
            'webhook' => EduplacesWebhookAdapter::class,

            'auth_url' => env('EDUPLACES_AUTH_URL', 'https://auth.eduplaces.io'),
            'api_url' => env('EDUPLACES_API_URL', 'https://api.eduplaces.io'),
            'client_id' => env('EDUPLACES_CLIENT_ID'),
            'client_secret' => env('EDUPLACES_CLIENT_SECRET'),

            // Request only what the app is actually granted: the token endpoint
            // rejects the whole request if one scope is ungranted, which would
            // take the integration down rather than degrade it.
            'scopes' => array_filter(array_map('trim', explode(' ', (string) env(
                'EDUPLACES_IDM_SCOPES',
                'urn:eduplaces:idm:v1:schools:read urn:eduplaces:idm:v1:groups:read '
                .'urn:eduplaces:idm:v1:users:read',
            )))),

            'webhook_secret' => env('EDUPLACES_WEBHOOK_SECRET'),

            // Where the school and the person live in the upstream id_token.
            'claims' => [
                'school' => 'school',
                'user' => 'sub',
            ],

            // Provider role -> aula userlevel, and the same value as the
            // per-room role, so rank cannot diverge between the two.
            'roles' => [
                'TEACHER' => (int) env('EDUPLACES_USERLEVEL_TEACHER', 40),
                'STUDENT' => (int) env('EDUPLACES_USERLEVEL_STUDENT', 20),
            ],
            'default_role' => (int) env('EDUPLACES_USERLEVEL_DEFAULT', 20),
        ],

    ],
];
