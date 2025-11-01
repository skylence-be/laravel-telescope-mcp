<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

final class EventsTool extends TelescopeAbstractTool
{
    protected string $entryType = 'event';

    public function getShortName(): string
    {
        return 'events';
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getShortName(),
            'description' => 'View events dispatched via Telescope',
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
            'content.broadcast',
            'created_at',
        ];
    }

    protected function getSearchableFields(): array
    {
        return [
            'name',
        ];
    }

    public function stats(array $arguments = []): array
    {
        $entries = $this->normalizeEntries($this->getEntries($arguments));

        if (empty($entries)) {
            return $this->formatter->formatStats([]);
        }

        $eventCounts = [];
        $broadcastCount = 0;

        foreach ($entries as $entry) {
            $name = $entry['content']['name'] ?? 'unknown';
            $broadcast = $entry['content']['broadcast'] ?? false;

            $eventCounts[$name] = ($eventCounts[$name] ?? 0) + 1;
            if ($broadcast) {
                $broadcastCount++;
            }
        }

        arsort($eventCounts);

        return $this->formatter->formatStats([
            'total_events' => count($entries),
            'by_event' => array_slice($eventCounts, 0, 10, true),
            'broadcast_count' => $broadcastCount,
            'unique_events' => count($eventCounts),
        ]);
    }
}
