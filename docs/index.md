# aula's backend Architecture

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
