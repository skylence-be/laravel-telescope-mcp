<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

final class CommandsTool extends TelescopeAbstractTool
{
    protected string $entryType = 'command';

    public function getShortName(): string
    {
        return 'commands';
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getShortName(),
            'description' => 'View artisan commands executed via Telescope',
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
                        'default' => '1h',
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
            'content.exit_code',
            'created_at',
        ];
    }

    protected function getSearchableFields(): array
    {
        return [
            'command',
        ];
    }

    public function stats(array $arguments = []): array
    {
        $entries = $this->normalizeEntries($this->getEntries($arguments));

        if (empty($entries)) {
            return $this->formatter->formatStats([]);
        }

        $commandCounts = [];
        $exitCodes = [];

        foreach ($entries as $entry) {
            $command = $entry['content']['command'] ?? 'unknown';
            $exitCode = $entry['content']['exit_code'] ?? 0;

            $commandCounts[$command] = ($commandCounts[$command] ?? 0) + 1;
            $exitCodes[$exitCode] = ($exitCodes[$exitCode] ?? 0) + 1;
        }

        arsort($commandCounts);

        return $this->formatter->formatStats([
            'total_commands' => count($entries),
            'by_command' => array_slice($commandCounts, 0, 10, true),
            'by_exit_code' => $exitCodes,
            'failed_count' => array_sum(array_filter($exitCodes, fn ($code) => $code !== 0, ARRAY_FILTER_USE_KEY)),
        ]);
    }
}
