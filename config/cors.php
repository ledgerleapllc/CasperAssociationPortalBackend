<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'DELETE',
        'OPTIONS'
    ],

    'allowed_origins' => [
        'http://caspermember.com',
        'https://caspermember.com',
        'http://casper.network',
        'https://casper.network',
        'http://members.casper.network',
        'https://members.casper.network',
        'http://members-staging.casper.network',
        'https://members-staging.casper.network',
        'http://api.shuftipro.com',
        'https://api.shuftipro.com',
        'http://api.hellosign.com',
        'https://api.hellosign.com'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
