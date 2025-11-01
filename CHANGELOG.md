# Changelog

All notable changes to `telescope-mcp` will be documented in this file.

## [1.0.0] - 2025-10-30

### Added
- Initial release
- MCP server with JSON-RPC 2.0 support
- Ping tool for health checks
- Echo tool for message testing
- Support for MCP protocol version 2024-11-05
- Configurable via environment variables
- Built-in logging support
- Multiple endpoint types:
  - JSON-RPC 2.0 endpoint
  - Manifest endpoint
  - Direct tool execution
  - Tool call via JSON-RPC

### Features
- Easy to extend with custom tools
- Abstract tool base class
- Automatic tool registration
- Follows MCP protocol specification
- Compatible with Laravel 10, 11, and 12
- PHP 8.1+ support
