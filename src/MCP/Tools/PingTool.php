<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

final class PingTool extends AbstractTool
{
    /**
     * Get the tool's short name.
     */
    public function getShortName(): string
    {
        return 'ping';
    }

    /**
     * Get the tool's schema definition.
     */
    public function getSchema(): array
    {
        return [
            'name' => $this->getShortName(),
            'description' => 'Simple ping tool to check if the MCP server is responding',
            'inputSchema' => [
                'type' => 'object',
                'properties' => (object) [],
                'required' => [],
            ],
        ];
    }

    /**
     * Execute the ping tool.
     */
    public function execute(array $params): array
    {
        return $this->formatResponse(
            'Pong! MCP server is up and running.',
            [
                'status' => 'ok',
                'timestamp' => now()->toIso8601String(),
                'server' => 'telescope-mcp',
            ]
        );
    }
}
