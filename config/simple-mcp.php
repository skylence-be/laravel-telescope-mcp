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
    'path' => env('SIMPLE_MCP_PATH', 'telescope-mcp'),

    /*
    |--------------------------------------------------------------------------
    | Enable/Disable MCP Server
    |--------------------------------------------------------------------------
    |
    | Control whether the MCP server is enabled.
    |
    */
    'enabled' => env('SIMPLE_MCP_ENABLED', true),

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
        'enabled' => env('SIMPLE_MCP_LOGGING_ENABLED', true),
        'channel' => env('SIMPLE_MCP_LOGGING_CHANNEL', 'stack'),
    ],
];
