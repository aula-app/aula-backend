# aula's backend Architecture

## Model main aula's controller

The main architecture behind aula's controllers is that most of the system requests pass through the controllers/models.php.

Methods from classes/models/[MODEL-NAME].php can be accessed with a POST request to:

POST /api/controllers/model.php

Request body:

```
 {
    arguments: {
        user_id: 165
    },
    method: "getDashboardByUser",
    model: "Idea"
 }

```

The access control to the methods is based on the user level, user id and the values of the arguments.

All models must contain a private attribute $openMethods with a list of methods that are open for all users,
a method `hasPermissions` that receives automatically from the model.php controller the `$user_id`, `$user_level` and
method arguments and then for each method of the model, it checks for the `methodPermission`. These methods
receives `$user_level`, `$user_id` and the arguments and return a boolean value, true if the user has permissions
to do that specific request or false otherwise. If a method from a model doesn't have a `methodPermission` written,
the default behaviour is not allowing the request to be processed.

## Password management

1. controllers/change_password.php

It uses the JWT token user id information to change a user password triggered using the user profile interface.

2. controllers/forgot_password.php

Triggers an email with an unique link to the user that can be used to reset a password.

3. controllers/set_password.php

Used for the first password setup for an user. When an user is created on the aula admin's interface, the users receives an unique URL with a secret and a link to set_password.php

## Upload files

controllers/upload.php is responsible for manging the media upload as user avatars to the filesystem of aula's server.
