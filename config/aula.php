<?php

return [

    // @TODO: nikola - we should move out content templates to be managed by
    //  the instance admin (into the database)
    'email' => [
        'template' => [
            'creation' => [
                'subject' => env('AULA_EMAIL_CREATION_SUBJECT', ''),
                // handled by user-created-welcome-password.blade.php
                // 'body' => env('AULA_EMAIL_CREATION_BODY', ''),
            ],
            'forgot_password' => [
                'subject' => env('AULA_EMAIL_FORGOT_PASSWORD_SUBJECT', ''),
                'body' => env('AULA_EMAIL_FORGOT_PASSWORD_BODY', ''),
            ],
        ],
        'from' => [
            'address' => env('MAIL_FROM_ADDRESS', ''),
            'name' => env('MAIL_FROM_NAME', ''),
        ],
        'reply_to' => [
            'address' => env('MAIL_REPLY_TO_ADDRESS', ''),
        ],
    ],
];
