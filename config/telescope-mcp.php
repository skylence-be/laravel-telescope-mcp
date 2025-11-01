<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Server Path
    |--------------------------------------------------------------------------
    |
    | The URI path where the MCP server will be accessible.
    |
    */
    'path' => env('TELESCOPE_MCP_PATH', 'telescope-mcp'),

    /*
    |--------------------------------------------------------------------------
    | Enable/Disable MCP Server
    |--------------------------------------------------------------------------
    |
    | Control whether the MCP server is enabled.
    |
    */
    'enabled' => env('TELESCOPE_MCP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware to apply to MCP routes.
    |
    */
    'middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable logging and configure the channel to use.
    |
    */
    'logging' => [
        'enabled' => env('TELESCOPE_MCP_LOGGING_ENABLED', true),
        'channel' => env('TELESCOPE_MCP_LOGGING_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slow Request Threshold
    |--------------------------------------------------------------------------
    |
    | Requests taking longer than this threshold (in milliseconds) will be
    | considered slow and can be retrieved with the 'slow' action.
    |
    */
    'slow_request_ms' => env('TELESCOPE_MCP_SLOW_REQUEST_MS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Authentication & Authorization
    |--------------------------------------------------------------------------
    |
    | Configure access control for the MCP endpoints. When enabled, requests
    | must include a valid bearer token or X-MCP-Token header.
    |
    | Generate a secure token: php artisan tinker --execute="echo bin2hex(random_bytes(32))"
    |
    */
    'auth' => [
        'enabled' => env('TELESCOPE_MCP_AUTH_ENABLED', true),
        'token' => env('TELESCOPE_MCP_API_TOKEN'),
    ],
];
