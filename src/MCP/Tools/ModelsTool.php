<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

final class ModelsTool extends TelescopeAbstractTool
{
    protected string $entryType = 'model';

    public function getShortName(): string
    {
        return 'models';
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getShortName(),
            'description' => 'View model events (created, updated, deleted) via Telescope',
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
            'content.model',
            'content.action',
            'created_at',
        ];
    }

    protected function getSearchableFields(): array
    {
        return [
            'model',
            'action',
        ];
    }

    public function stats(array $arguments = []): array
    {
        $entries = $this->normalizeEntries($this->getEntries($arguments));

        if (empty($entries)) {
            return $this->formatter->formatStats([]);
        }

        $modelCounts = [];
        $actionCounts = [];

        foreach ($entries as $entry) {
            $model = $entry['content']['model'] ?? 'unknown';
            $action = $entry['content']['action'] ?? 'unknown';

            $modelCounts[$model] = ($modelCounts[$model] ?? 0) + 1;
            $actionCounts[$action] = ($actionCounts[$action] ?? 0) + 1;
        }

        arsort($modelCounts);

        return $this->formatter->formatStats([
            'total_events' => count($entries),
            'by_model' => array_slice($modelCounts, 0, 10, true),
            'by_action' => $actionCounts,
        ]);
    }
}
