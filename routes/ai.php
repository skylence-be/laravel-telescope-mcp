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
