<?php

return [
    'dsn' => env('SENTRY_DSN'),

    // Set the environment
    'environment' => env('APP_ENV'),

    // Release version for Sentry
    'release' => env('APP_VERSION', '1.0.0'),

    // Capture error levels
    'error_types' => E_ALL & ~E_NOTICE & ~E_DEPRECATED,

    // Ignore these exceptions
    'ignore_exceptions' => [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
    ],

    // Sample rate (1.0 = send all, 0.1 = send 10%)
    'sample_rate' => env('SENTRY_SAMPLE_RATE', 1.0),

    // Trace sample rate
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),

    // Ignore paths (don't send errors from these)
    'ignore_paths' => [
        'vendor',
    ],

    // Breadcrumb options
    'breadcrumbs' => [
        'logs' => true,
        'sql_queries' => true,
        'queue_jobs' => true,
    ],

    // User context (attached to all errors)
    'attach_user_context' => true,
];
