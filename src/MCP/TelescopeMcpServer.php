<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP;

use Skylence\TelescopeMcp\MCP\Tools\AbstractTool;
use Skylence\TelescopeMcp\MCP\Tools\EchoTool;
use Skylence\TelescopeMcp\MCP\Tools\PingTool;
use Skylence\TelescopeMcp\MCP\Tools\RequestsTool;
use Skylence\TelescopeMcp\Services\PaginationManager;
use Skylence\TelescopeMcp\Services\ResponseFormatter;

final class TelescopeMcpServer
{
    /**
     * Registered tools.
     *
     * @var array<string, AbstractTool>
     */
    private array $tools = [];

    /**
     * Create a new TelescopeMcpServer instance.
     */
    public function __construct()
    {
        $this->registerTools();
    }

    /**
     * Register all available tools.
     */
    private function registerTools(): void
    {
        $this->registerTool(new PingTool());
        $this->registerTool(new EchoTool());

        // Register Telescope tools if available
        if (class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $config = config('telescope-mcp', []);
            $pagination = app(PaginationManager::class);
            $formatter = app(ResponseFormatter::class);

            $this->registerTool(new RequestsTool($config, $pagination, $formatter));
        }
    }

    /**
     * Register a single tool.
     */
    private function registerTool(AbstractTool $tool): void
    {
        $this->tools[$tool->getShortName()] = $tool;
    }

    /**
     * Get the server manifest.
     */
    public function getManifest(): array
    {
        return [
            'name' => 'telescope-mcp',
            'version' => '1.0.0',
            'description' => 'A simple MCP server for Laravel',
            'tools' => $this->getToolsAsObject(),
            'resources' => $this->getResourcesAsObject(),
            'prompts' => $this->getPromptsAsObject(),
            'metadata' => $this->getMetadata(),
        ];
    }

    /**
     * Get all registered tools schemas as array (for tools/list JSON-RPC method).
     */
    public function getTools(): array
    {
        return $this->getToolsAsArray();
    }

    /**
     * Get tools as an array (for tools/list JSON-RPC method).
     */
    private function getToolsAsArray(): array
    {
        $tools = [];
        foreach ($this->tools as $tool) {
            $schema = $tool->getSchema();
            $tools[] = [
                'name' => $schema['name'],
                'description' => $schema['description'],
                'inputSchema' => $schema['inputSchema'] ?? [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ];
        }

        return $tools;
    }

    /**
     * Get tools as an object keyed by tool name (for manifest capabilities).
     */
    private function getToolsAsObject(): array
    {
        $tools = [];
        foreach ($this->tools as $tool) {
            $schema = $tool->getSchema();
            $tools[$tool->getShortName()] = [
                'name' => $schema['name'],
                'description' => $schema['description'],
                'inputSchema' => $schema['inputSchema'] ?? [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ];
        }

        return $tools;
    }

    /**
     * Get available resources as an object keyed by URI (for MCP protocol).
     */
    private function getResourcesAsObject(): array
    {
        return [
            'telescope-mcp://status' => [
                'uri' => 'telescope-mcp://status',
                'name' => 'Server Status',
                'description' => 'Current status and health of the Simple MCP server',
                'mimeType' => 'application/json',
            ],
        ];
    }

    /**
     * Get available prompts as an object keyed by prompt name (for MCP protocol).
     */
    private function getPromptsAsObject(): array
    {
        return [
            'test-server' => [
                'name' => 'test-server',
                'description' => 'Test the Simple MCP server with ping and echo',
                'arguments' => [],
            ],
        ];
    }

    /**
     * Get server metadata.
     */
    private function getMetadata(): array
    {
        return [
            'author' => 'Skylence',
            'repository' => 'https://github.com/skylence-be/telescope-mcp',
            'license' => 'MIT',
            'tags' => [
                'laravel',
                'mcp',
                'model-context-protocol',
                'json-rpc',
                'ai',
                'tools',
            ],
        ];
    }

    /**
     * Execute a tool by name.
     */
    public function executeTool(string $toolName, array $params = []): array
    {
        if (! isset($this->tools[$toolName])) {
            throw new \InvalidArgumentException("Tool '{$toolName}' not found");
        }

        return $this->tools[$toolName]->execute($params);
    }

    /**
     * Check if a tool exists.
     */
    public function hasTool(string $toolName): bool
    {
        return isset($this->tools[$toolName]);
    }
}
