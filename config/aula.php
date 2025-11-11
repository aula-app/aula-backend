<?php

return [

    // @TODO: nikola - we should move out content templates to be managed by
    //  the instance admin (into the database)
    'email' => [
        'template' => [
            'creation' => [
                'subject' => env('AULA_EMAIL_CREATION_SUBJECT', ''),
                'body' => env('AULA_EMAIL_CREATION_BODY', ''),
            ],
            'forgot_password' => [
                'subject' => env('AULA_EMAIL_FORGOT_PASSWORD_SUBJECT', ''),
                'body' => env('AULA_EMAIL_FORGOT_PASSWORD_BODY', ''),
            ],
        ],
    ],
];
