<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

final class ScheduleTool extends TelescopeAbstractTool
{
    protected string $entryType = 'schedule';

    public function getShortName(): string
    {
        return 'schedule';
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getShortName(),
            'description' => 'View scheduled tasks executed via Telescope',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['summary', 'list', 'detail', 'stats', 'search'],
                        'description' => 'Action to perform',
                        'default' => 'list',
                    ],
                    'period' => [
                        'type' => 'string',
                        'enum' => ['5m', '15m', '1h', '6h', '24h', '7d', '14d', '21d', '30d', '3M', '6M', '12M'],
                        'description' => 'Time period for analysis',
                        'default' => '24h',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Number of entries to return (max 25)',
                        'default' => 10,
                    ],
                    'offset' => [
                        'type' => 'integer',
                        'description' => 'Offset for pagination',
                        'default' => 0,
                    ],
                    'id' => [
                        'type' => 'string',
                        'description' => 'Entry ID for detail view',
                    ],
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    protected function getListFields(): array
    {
        return [
            'id',
            'content.command',
            'content.description',
            'created_at',
        ];
    }

    protected function getSearchableFields(): array
    {
        return [
            'command',
            'description',
        ];
    }

    public function stats(array $arguments = []): array
    {
        $entries = $this->normalizeEntries($this->getEntries($arguments));

        if (empty($entries)) {
            return $this->formatter->formatStats([]);
        }

        $commandCounts = [];

        foreach ($entries as $entry) {
            $command = $entry['content']['command'] ?? 'unknown';
            $commandCounts[$command] = ($commandCounts[$command] ?? 0) + 1;
        }

        arsort($commandCounts);

        return $this->formatter->formatStats([
            'total_executions' => count($entries),
            'by_command' => array_slice($commandCounts, 0, 10, true),
            'unique_tasks' => count($commandCounts),
        ]);
    }
}
