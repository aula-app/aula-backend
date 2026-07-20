<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'keycloak' => [
        'base_url' => env('KEYCLOAK_BASE_URL', 'https://sso.aula.de/auth'),
        'realms' => env('KEYCLOAK_REALM', 'aula'),
        'client_id' => env('KEYCLOAK_CLIENT_ID', 'aula-backend'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect' => env('KEYCLOAK_REDIRECT_URI'),
        'clock_skew_seconds' => env('SSO_CLOCK_SKEW_SECONDS', 300),
    ],

    'eduplaces' => [
        'idp_alias' => env('EDUPLACES_IDP_ALIAS', 'eduplaces'),
        'allowed_issuers' => array_filter(array_map('trim', explode(',', (string) env(
            'EDUPLACES_ALLOWED_ISSUERS',
            'https://auth.eduplaces.io,https://auth.sandbox.eduplaces.dev',
        )))),

        // IDM API. Webhook payloads only carry an id and the names of the
        // properties that changed, so every event is followed by a read-back
        // against these endpoints.
        'auth_base_url' => env('EDUPLACES_AUTH_URL', 'https://auth.eduplaces.io'),
        'api_base_url' => env('EDUPLACES_API_URL', 'https://api.eduplaces.io'),
        'client_id' => env('EDUPLACES_CLIENT_ID'),
        'client_secret' => env('EDUPLACES_CLIENT_SECRET'),
        'scopes' => array_filter(array_map('trim', explode(' ', (string) env(
            'EDUPLACES_IDM_SCOPES',
            'urn:eduplaces:idm:v1:schools:read urn:eduplaces:idm:v1:groups:read '
            .'urn:eduplaces:idm:v1:people:read urn:eduplaces:idm:v1:users:read',
        )))),

        // Shared secret Eduplaces signs webhook bodies with (X-EP-Signature-Sha256).
        'webhook_secret' => env('EDUPLACES_WEBHOOK_SECRET'),

        // Eduplaces role -> aula userlevel. See App\Enums\UserLevel.
        'role_userlevels' => [
            'TEACHER' => (int) env('EDUPLACES_USERLEVEL_TEACHER', 40),
            'STUDENT' => (int) env('EDUPLACES_USERLEVEL_STUDENT', 20),
        ],
        'default_userlevel' => (int) env('EDUPLACES_USERLEVEL_DEFAULT', 20),
    ],

];
