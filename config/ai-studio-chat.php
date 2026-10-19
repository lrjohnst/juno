<?php

return [
    'base_url' => env('AI_STUDIO_URL', 'https://ai-studio.phitech.cloud'),
    'api_key' => env('AI_STUDIO_API_KEY'),

    'mode' => env('AI_STUDIO_CHAT_MODE', 'prompt'),
    'slug' => env('AI_STUDIO_CHAT_SLUG'),

    'prefix' => 'chat',

    'middleware' => ['web', \LucasJohnston\Sso\Http\Middleware\SsoAuthenticate::class],

    'user_resolver' => function (\Illuminate\Http\Request $request) {
        return $request->user()?->email;
    },

    'actions' => [],
];
