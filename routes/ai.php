<?php

declare(strict_types=1);

use Laravel\Mcp\Facades\Mcp;
use Skylence\TelescopeMcp\MCP\TelescopeServer;

/*
|--------------------------------------------------------------------------
| Telescope MCP Server Registration (Stdio)
|--------------------------------------------------------------------------
|
| This file registers the Telescope MCP server for stdio access via
| Laravel's official MCP package. This enables AI assistants to connect
| to your Telescope monitoring tools through the Model Context Protocol.
|
| Usage:
|   php artisan mcp:start telescope
|   php artisan mcp:inspector telescope
|
| Configuration in your AI client (e.g., Claude Desktop):
| {
|   "mcpServers": {
|     "laravel-telescope": {
|       "command": "php",
|       "args": ["artisan", "mcp:start", "telescope"]
|     }
|   }
| }
|
*/

Mcp::local('telescope', TelescopeServer::class);

/*
|--------------------------------------------------------------------------
| Telescope MCP Server Registration (Streamable HTTP)
|--------------------------------------------------------------------------
|
| Registers the same Telescope MCP server for Streamable HTTP access,
| enabling remote AI clients to connect via HTTP with SSE streaming.
|
| The route path is configurable via the telescope-mcp config and
| auth middleware is applied when enabled.
|
*/

$httpPath = config('telescope-mcp.http.path', config('telescope-mcp.path', 'telescope-mcp'));

if (config('telescope-mcp.http.enabled', true)) {
    $route = Mcp::web($httpPath, TelescopeServer::class);

    $middleware = config('telescope-mcp.http.middleware', ['api']);
    if (config('telescope-mcp.auth.enabled', true)) {
        $middleware[] = 'telescope-mcp.auth';
    }
    $route->middleware($middleware);
}
