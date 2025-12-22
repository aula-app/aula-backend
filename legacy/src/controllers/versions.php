<?php

echo json_encode([
    'aula-backend' => [
        // injected by docker build argument DOCKER_TAG
        'running' => getenv('APP_VERSION', 'unknown'),
        'latest' => 'TODO',
    ],
    'aula-frontend' => [
        // minimum FE version that is free of Backward Compatibility Breaking Changes
        // FE should refuse to work if its version is lower
        // @TODO: set this to the version of FE that implements the killswitch
        //   ref: https://github.com/aula-app/aula-frontend/issues/761
        'minimum' => 'v1.4.4',
        // recommended FE version that supports all new features
        'recommended' => 'v1.6.1',
    ],
]);
