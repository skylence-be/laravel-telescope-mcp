<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

final class ViewsTool extends TelescopeAbstractTool
{
    protected string $entryType = 'view';

    public function getShortName(): string
    {
        return 'views';
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getShortName(),
            'description' => 'View rendered views/templates via Telescope',
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
            'content.name',
            'content.path',
            'created_at',
        ];
    }

    protected function getSearchableFields(): array
    {
        return [
            'name',
            'path',
        ];
    }

    public function stats(array $arguments = []): array
    {
        $entries = $this->normalizeEntries($this->getEntries($arguments));

        if (empty($entries)) {
            return $this->formatter->formatStats([]);
        }

        $viewCounts = [];

        foreach ($entries as $entry) {
            $name = $entry['content']['name'] ?? 'unknown';
            $viewCounts[$name] = ($viewCounts[$name] ?? 0) + 1;
        }

        arsort($viewCounts);

        return $this->formatter->formatStats([
            'total_renders' => count($entries),
            'by_view' => array_slice($viewCounts, 0, 10, true),
            'unique_views' => count($viewCounts),
        ]);
    }
}
