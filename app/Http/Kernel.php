<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middleware = [
        // global middleware
    ];

    protected $middlewareGroups = [
        'web' => [
            // web middleware
        ],
        'api' => [
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    protected $routeMiddleware = [
      'refresh.token' => \App\Http\Middleware\RefreshToken::class, // <-- add this line
        // you add your 'refresh.token' here
    ];
}
