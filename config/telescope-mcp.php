<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Server Path (Legacy - use http.path instead)
    |--------------------------------------------------------------------------
    |
    | The URI path where the HTTP MCP server will be accessible.
    | This is kept for backward compatibility.
    |
    */
    'path' => env('TELESCOPE_MCP_PATH', 'telescope-mcp'),

    /*
    |--------------------------------------------------------------------------
    | Enable/Disable MCP Server
    |--------------------------------------------------------------------------
    |
    | Control whether the MCP server is enabled (both stdio and HTTP).
    |
    */
    'enabled' => env('TELESCOPE_MCP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Middleware (Legacy - use http.middleware instead)
    |--------------------------------------------------------------------------
    |
    | Middleware to apply to HTTP MCP routes.
    | This is kept for backward compatibility.
    |
    */
    'middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | HTTP MCP Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for HTTP-based MCP access (JSON-RPC 2.0).
    | This allows remote access to Telescope tools via HTTP endpoints.
    |
    */
    'http' => [
        'enabled' => env('TELESCOPE_MCP_HTTP_ENABLED', true),
        'path' => env('TELESCOPE_MCP_HTTP_PATH', env('TELESCOPE_MCP_PATH', 'telescope-mcp')),
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable logging and configure separate channels for access and error logs.
    | Access logs: info, debug (requests, responses, tool calls)
    | Error logs: error, warning (failures, exceptions, auth issues)
    |
    | Logs are stored in:
    | - storage/logs/telescope-mcp-access.log
    | - storage/logs/telescope-mcp-error.log
    |
    */
    'logging' => [
        'enabled' => env('TELESCOPE_MCP_LOGGING_ENABLED', true),
        'access_channel' => 'telescope-mcp-access',
        'error_channel' => 'telescope-mcp-error',
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
    | Slow Query Threshold
    |--------------------------------------------------------------------------
    |
    | Database queries taking longer than this threshold (in milliseconds)
    | will be considered slow and can be retrieved with the 'slow' action.
    |
    */
    'slow_query_ms' => env('TELESCOPE_MCP_SLOW_QUERY_MS', 100),

    /*
    |--------------------------------------------------------------------------
    | High Memory Threshold
    |--------------------------------------------------------------------------
    |
    | Requests using more memory than this threshold (in megabytes) will be
    | considered high memory usage.
    |
    */
    'high_memory_mb' => env('TELESCOPE_MCP_HIGH_MEMORY_MB', 50),

    /*
    |--------------------------------------------------------------------------
    | N+1 Query Detection Threshold
    |--------------------------------------------------------------------------
    |
    | The minimum number of similar queries to trigger N+1 detection.
    |
    */
    'n_plus_one_threshold' => env('TELESCOPE_MCP_N_PLUS_ONE_THRESHOLD', 3),

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

    /*
    |--------------------------------------------------------------------------
    | Overview Tool Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how the overview tool analyzes and categorizes routes.
    | Route groups are matched in order - first match wins.
    |
    */
    'overview' => [
        /*
        | Route Groups
        |
        | Define groups of routes for separate analysis. Routes are categorized
        | by middleware (ANY match) and/or URI prefix. First matching group wins.
        */
        'route_groups' => [
            'api' => [
                'middleware' => ['api'],
                'uri_prefix' => null, // Optional additional filter
            ],
            'web' => [
                'middleware' => ['web'],
                'uri_prefix' => null,
            ],
        ],

        /*
        | Global Exclusions
        |
        | Routes matching these patterns will be excluded from all analyses.
        | Supports wildcards (*) for pattern matching.
        */
        'exclude' => [
            'uris' => [
                'telescope-mcp/*',
                'telescope/*',
                'horizon/*',
                'pulse/*',
                '_debugbar/*',
                'livewire/*',
            ],
            'middleware' => [
                // Exclude routes with specific middleware
            ],
            'controller_actions' => [
                // e.g., 'Closure' to exclude closure-based routes
            ],
        ],

        /*
        | Context-Aware Thresholds
        |
        | Different performance expectations for different route types.
        | These override the global thresholds when analyzing specific route groups.
        */
        'thresholds' => [
            'api' => [
                'slow_request_ms' => 500,      // APIs should respond quickly
                'acceptable_error_rate' => 0.01, // 1% error rate
            ],
            'web' => [
                'slow_request_ms' => 1500,     // Web pages can be slower
                'acceptable_error_rate' => 0.05, // 5% error rate
            ],
        ],

        /*
        | Matching Strategy
        |
        | How to match middleware:
        | - 'any': Request matches if it has ANY of the group's middleware
        | - 'all': Request matches only if it has ALL of the group's middleware
        */
        'matching_strategy' => 'any',
    ],
];
