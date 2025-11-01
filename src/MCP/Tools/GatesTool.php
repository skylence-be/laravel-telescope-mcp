<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

final class GatesTool extends TelescopeAbstractTool
{
    protected string $entryType = 'gate';

    public function getShortName(): string
    {
        return 'gates';
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getShortName(),
            'description' => 'View gate authorization checks via Telescope',
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
            'content.ability',
            'content.result',
            'created_at',
        ];
    }

    protected function getSearchableFields(): array
    {
        return [
            'ability',
        ];
    }

    public function stats(array $arguments = []): array
    {
        $entries = $this->normalizeEntries($this->getEntries($arguments));

        if (empty($entries)) {
            return $this->formatter->formatStats([]);
        }

        $abilityCounts = [];
        $allowedCount = 0;
        $deniedCount = 0;

        foreach ($entries as $entry) {
            $ability = $entry['content']['ability'] ?? 'unknown';
            $result = $entry['content']['result'] ?? null;

            $abilityCounts[$ability] = ($abilityCounts[$ability] ?? 0) + 1;

            if ($result === 'allowed') {
                $allowedCount++;
            } elseif ($result === 'denied') {
                $deniedCount++;
            }
        }

        arsort($abilityCounts);

        return $this->formatter->formatStats([
            'total_checks' => count($entries),
            'by_ability' => array_slice($abilityCounts, 0, 10, true),
            'allowed' => $allowedCount,
            'denied' => $deniedCount,
        ]);
    }
}
