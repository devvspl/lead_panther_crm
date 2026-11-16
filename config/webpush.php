<?php
return [
    'enabled' => env('WEB_PUSH_ENABLED', true),
    'public_key' => env('WEB_PUSH_PUBLIC_KEY'),
    'private_key' => env('WEB_PUSH_PRIVATE_KEY'),
    'subject' => env('WEB_PUSH_SUBJECT'),
    'openssl_conf' => env('WEB_PUSH_OPENSSL_CONF'),
];
