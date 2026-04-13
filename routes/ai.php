<?php

declare(strict_types=1);

use Laravel\Mcp\Facades\Mcp;
use Skylence\TelescopeMcp\MCP\TelescopeServer;

/*
|--------------------------------------------------------------------------
| Telescope MCP Server Registration (Streamable HTTP)
|--------------------------------------------------------------------------
|
| Registers the Telescope MCP server for Streamable HTTP access,
| enabling remote AI clients to connect via HTTP with SSE streaming.
|
| The stdio server is registered in TelescopeMcpServiceProvider::boot()
| to ensure it is available in console context (artisan commands).
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
