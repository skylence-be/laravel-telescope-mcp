# Stdio and HTTP MCP Support

The Laravel Telescope MCP package now supports **both stdio and HTTP access methods**, similar to laravel-optimize-mcp.

## Access Methods

### 1. Stdio MCP (via Laravel MCP Package)

**Use this for**: Local development with AI assistants (Claude Desktop, Cursor, VS Code, etc.)

**Start the server**:
```bash
php artisan mcp:start telescope
```

**Inspect the server**:
```bash
php artisan mcp:inspector telescope
```

**AI Client Configuration** (e.g., Claude Desktop `claude_desktop_config.json`):
```json
{
  "mcpServers": {
    "laravel-telescope": {
      "command": "php",
      "args": ["artisan", "mcp:start", "telescope"],
      "cwd": "/path/to/your/laravel/project"
    }
  }
}
```

**Features**:
- ✅ Native Laravel MCP integration
- ✅ Real-time stdio communication
- ✅ All Telescope monitoring tools available
- ✅ Works with Claude Desktop, Cursor, VS Code MCP plugins

### 2. HTTP MCP (JSON-RPC 2.0)

**Use this for**: Remote access, staging/production servers, custom integrations

**Base URL**: `http://your-app.test/telescope-mcp`

**Available Endpoints**:

#### Get Manifest
```bash
GET /telescope-mcp/manifest.json
```

#### List Tools
```bash
POST /telescope-mcp/
Content-Type: application/json
X-MCP-Token: your-token-here

{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/list",
  "params": {}
}
```

#### Execute Tool (JSON-RPC)
```bash
POST /telescope-mcp/tools/call
Content-Type: application/json
X-MCP-Token: your-token-here

{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "tools/call",
  "params": {
    "name": "overview",
    "arguments": {
      "hours": 24,
      "group": "api"
    }
  }
}
```

#### Execute Tool (REST)
```bash
POST /telescope-mcp/tools/overview
Content-Type: application/json
X-MCP-Token: your-token-here

{
  "hours": 24,
  "group": "api"
}
```

**Features**:
- ✅ JSON-RPC 2.0 compliant
- ✅ Token-based authentication
- ✅ All Telescope monitoring tools available
- ✅ REST-style tool execution
- ✅ Works with any HTTP client

## Configuration

### Enable/Disable Access Methods

In `config/telescope-mcp.php`:

```php
return [
    // Enable/disable the entire MCP server (both stdio and HTTP)
    'enabled' => env('TELESCOPE_MCP_ENABLED', true),

    // HTTP MCP Configuration
    'http' => [
        'enabled' => env('TELESCOPE_MCP_HTTP_ENABLED', true),
        'path' => env('TELESCOPE_MCP_HTTP_PATH', 'telescope-mcp'),
        'middleware' => ['api'],
    ],

    // Authentication (applies to HTTP only)
    'auth' => [
        'enabled' => env('TELESCOPE_MCP_AUTH_ENABLED', true),
        'token' => env('TELESCOPE_MCP_API_TOKEN'),
    ],
];
```

### Environment Variables

Add to your `.env`:

```env
# Enable/disable MCP server
TELESCOPE_MCP_ENABLED=true

# HTTP MCP settings
TELESCOPE_MCP_HTTP_ENABLED=true
TELESCOPE_MCP_HTTP_PATH=telescope-mcp

# Authentication
TELESCOPE_MCP_AUTH_ENABLED=true
TELESCOPE_MCP_API_TOKEN=your-secure-token-here
```

**Generate a secure token**:
```bash
php artisan tinker --execute="echo bin2hex(random_bytes(32))"
```

## Architecture

### Stdio Implementation

- **Server Class**: `Skylence\TelescopeMcp\MCP\TelescopeServer`
  - Extends `Laravel\Mcp\Server`
  - Registered in `routes/ai.php` via `Mcp::local('telescope', TelescopeServer::class)`

- **Tool Adapters**: Bridge existing `AbstractTool` implementations to Laravel's `Tool` interface
  - Located in `src/MCP/Tools/Adapters/`
  - Example: `OverviewToolAdapter`, `RequestsToolAdapter`, etc.

### HTTP Implementation

- **Routes**: Defined in `routes/http.php`
- **Controller**: `Skylence\TelescopeMcp\Http\Controllers\McpController`
- **Server Class**: `Skylence\TelescopeMcp\MCP\TelescopeMcpServer` (existing)
- **Authentication**: `Skylence\TelescopeMcp\Http\Middleware\AuthenticateMcp`

### Shared Components

Both access methods use the same underlying tool implementations:
- `src/MCP/Tools/AbstractTool.php` - Base tool interface
- `src/MCP/Tools/*.php` - All Telescope monitoring tools
- `src/Services/` - Shared services (pagination, formatting, analysis, etc.)

## Available Tools

Both stdio and HTTP access provide these tools:

- **overview** - Application overview with performance insights
- **requests** - HTTP requests monitoring
- **queries** - Database queries analysis
- **logs** - Application logs
- **exceptions** - Exception tracking
- **commands** - Artisan commands
- **schedule** - Scheduled tasks
- **jobs** - Queue jobs
- **cache** - Cache operations
- **events** - Event dispatching
- **gates** - Authorization gates
- **models** - Model operations
- **notifications** - Notifications sent
- **redis** - Redis operations
- **views** - View rendering
- **maintenance** - Telescope maintenance operations

## Testing

### Test Stdio MCP
```bash
php artisan mcp:start telescope
# In another terminal:
php artisan mcp:inspector telescope
```

### Test HTTP MCP
```bash
curl -X POST "http://your-app.test/telescope-mcp/tools/call" \
  -H "Content-Type: application/json" \
  -H "X-MCP-Token: your-token" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/call",
    "params": {
      "name": "maintenance",
      "arguments": {"action": "status"}
    }
  }'
```

### Test Routes Registration
```bash
php artisan route:list | grep telescope-mcp
```

## Benefits

### Stdio Benefits
- 🚀 Fast local development
- 🔒 No network exposure required
- 💻 Perfect for AI assistants
- 🎯 Direct integration with IDEs

### HTTP Benefits
- 🌐 Remote server access
- 🔧 Works on staging/production
- 🛠️ Easy integration with any HTTP client
- 📡 RESTful and JSON-RPC support

## Backward Compatibility

All existing HTTP MCP functionality remains unchanged. The stdio support is additive and doesn't affect existing HTTP users.

Legacy configuration keys (`path`, `middleware`) are still supported and will be used as fallbacks if the new `http.*` keys are not specified.

## Migration from HTTP-only to Both

No migration needed! Just start using the stdio commands if you want local MCP access:

```bash
# This now works out of the box:
php artisan mcp:start telescope
```

Your existing HTTP endpoints continue to work as before.

## Troubleshooting

### Stdio issues

**Problem**: `php artisan mcp:start telescope` fails
- Ensure `laravel/mcp` package is installed: `composer require laravel/mcp`
- Check `routes/ai.php` exists and is loaded
- Verify Laravel Telescope is installed

**Problem**: Tools not showing up in MCP inspector
- Check `src/MCP/Tools/Adapters/` directory exists
- Ensure all tool adapter classes are created
- Run `composer dump-autoload`

### HTTP issues

**Problem**: 404 on HTTP endpoints
- Check `config/telescope-mcp.php` has `http.enabled => true`
- Verify routes are registered: `php artisan route:list | grep telescope-mcp`
- Clear config cache: `php artisan config:clear`

**Problem**: 401 Unauthorized
- Set `TELESCOPE_MCP_API_TOKEN` in `.env`
- Include token in request header: `X-MCP-Token: your-token`
- Or disable auth: `TELESCOPE_MCP_AUTH_ENABLED=false` (not recommended for production)

## Security Notes

### Stdio Security
- Stdio MCP only works locally - no network exposure
- Safe for development environments
- No authentication needed (process-level isolation)

### HTTP Security
- **Always** use authentication in production
- Use HTTPS for remote access
- Rotate tokens regularly
- Consider IP allowlisting via middleware
- Add rate limiting: `'middleware' => ['api', 'throttle:60,1']`

## Credits

Architecture inspired by `laravel-optimize-mcp` package structure.
