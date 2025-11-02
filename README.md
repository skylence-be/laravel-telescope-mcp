# Simple MCP

A super simple MCP (Model Context Protocol) server package for Laravel, based on the Laravel Telescope MCP architecture.

## Features

- 🚀 Laravel Telescope MCP server
- 📊 Monitor and analyze your Laravel application through MCP
- 📦 Easy to extend with custom tools
- 🔧 Follows JSON-RPC 2.0 specification
- 📝 Built-in logging support
- ⚙️ Configurable via config file

## Installation

### 1. Install via Composer

If you're developing this package locally, add it to your Laravel project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-packages/telescope-mcp"
        }
    ]
}
```

Then require it:

```bash
composer require skylence/telescope-mcp
```

### 2. Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=telescope-mcp-config
```

This will create `config/telescope-mcp.php` where you can customize:
- Server path (default: `telescope-mcp`)
- Enable/disable the server
- Middleware configuration
- Logging settings

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
SIMPLE_MCP_PATH=telescope-mcp
SIMPLE_MCP_ENABLED=true
SIMPLE_MCP_LOGGING_ENABLED=true
SIMPLE_MCP_LOGGING_CHANNEL=stack
```

## Usage

### Available Endpoints

Once installed, the MCP server is available at `/telescope-mcp` (or your configured path):

```
POST /telescope-mcp                - JSON-RPC 2.0 endpoint (supports all methods)
GET  /telescope-mcp/manifest.json  - Get server manifest (MCP protocol format)
POST /telescope-mcp/manifest.json  - JSON-RPC 2.0 endpoint (same as POST /)
POST /telescope-mcp/tools/call     - Call a tool via JSON-RPC
POST /telescope-mcp/tools/{tool}   - Direct tool execution
```

### Testing the Server

#### Get the Manifest

```bash
curl http://localhost:8000/telescope-mcp/manifest.json
```

This will return the server manifest with all available tools, resources, and prompts in MCP protocol format.

## Creating Custom Tools

### 1. Create a New Tool Class

Create a new file in `src/MCP/Tools/YourTool.php`:

```php
<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

final class YourTool extends AbstractTool
{
    public function getShortName(): string
    {
        return 'your-tool';
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'description' => 'Your tool description',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'param1' => [
                        'type' => 'string',
                        'description' => 'Parameter description',
                    ],
                ],
                'required' => ['param1'],
            ],
        ];
    }

    public function execute(array $params): array
    {
        // Validate parameters
        if (!isset($params['param1'])) {
            return $this->formatError('param1 is required');
        }

        // Your logic here
        $result = "Processing: {$params['param1']}";

        return $this->formatResponse($result, [
            'processed' => true,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
```

### 2. Register Your Tool

In `src/MCP/TelescopeMcpServer.php`, add your tool to the `registerTools()` method:

```php
private function registerTools(): void
{
    $this->registerTool(new YourTool()); // Add this line

    // Or register conditionally if it depends on Telescope
    if (class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
        $this->registerTool(new YourTool());
    }
}
```

### 3. Test Your Tool

```bash
curl -X POST http://localhost:8000/telescope-mcp/tools/your-tool \
  -H "Content-Type: application/json" \
  -d '{"param1": "test value"}'
```

## Architecture

```
telescope-mcp/
├── config/
│   └── telescope-mcp.php              # Configuration file
├── routes/
│   └── api.php                      # Route definitions
├── src/
│   ├── Http/
│   │   └── Controllers/
│   │       └── McpController.php    # HTTP handler
│   ├── MCP/
│   │   ├── TelescopeMcpServer.php      # Core server
│   │   └── Tools/
│   │       ├── AbstractTool.php     # Base tool class
│   │       └── [Various Telescope tools]
│   ├── Support/
│   │   ├── JsonRpcResponse.php      # JSON-RPC helpers
│   │   └── Logger.php               # Logging helper
│   └── TelescopeMcpServiceProvider.php # Service provider
└── composer.json
```

## JSON-RPC 2.0 Compliance

This package follows the JSON-RPC 2.0 specification:

- **Request Format:**
  ```json
  {
      "jsonrpc": "2.0",
      "method": "tools/call",
      "params": {...},
      "id": 1
  }
  ```

- **Success Response:**
  ```json
  {
      "jsonrpc": "2.0",
      "result": {...},
      "id": 1
  }
  ```

- **Error Response:**
  ```json
  {
      "jsonrpc": "2.0",
      "error": {
          "code": -32600,
          "message": "Invalid Request"
      },
      "id": 1
  }
  ```

## Error Codes

- `-32700`: Parse error
- `-32600`: Invalid request
- `-32601`: Method not found
- `-32602`: Invalid params
- `-32603`: Internal error

## Logging

All MCP requests and responses are logged if logging is enabled. Check your Laravel logs:

```bash
tail -f storage/logs/laravel.log
```

## Requirements

- PHP 8.1 or higher
- Laravel 10, 11, or 12

## License

MIT

## Credits

Based on the [Laravel Telescope MCP](https://github.com/lucianotonet/laravel-telescope-mcp) architecture by Luciano Tonet.

## Contributing

Feel free to extend this package with more tools and features!
