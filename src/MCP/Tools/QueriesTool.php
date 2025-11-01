<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

final class QueriesTool extends TelescopeAbstractTool
{
    protected string $entryType = 'query';

    public function getShortName(): string
    {
        return 'queries';
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getShortName(),
            'description' => 'View and analyze database queries from Telescope',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['summary', 'list', 'detail', 'stats', 'search', 'slow', 'duplicates'],
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
                        'description' => 'Search query (searches SQL)',
                    ],
                    'connection' => [
                        'type' => 'string',
                        'description' => 'Filter by database connection',
                    ],
                    'min_time' => [
                        'type' => 'number',
                        'description' => 'Minimum query time in ms',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    public function execute(array $arguments = []): array
    {
        $action = $arguments['action'] ?? 'list';

        return match ($action) {
            'slow' => $this->getSlowQueries($arguments),
            'duplicates' => $this->getDuplicateQueries($arguments),
            default => parent::execute($arguments),
        };
    }

    /**
     * Get slow queries.
     */
    protected function getSlowQueries(array $arguments): array
    {
        $threshold = $arguments['min_time'] ?? $this->config['slow_query_ms'] ?? 100;
        $limit = $this->pagination->getLimit($arguments['limit'] ?? 10);

        $entries = $this->normalizeEntries($this->getEntries($arguments));

        $slowQueries = array_filter($entries, function ($entry) use ($threshold) {
            return ($entry['content']['time'] ?? 0) >= $threshold;
        });

        usort($slowQueries, function ($a, $b) {
            return $b['content']['time'] <=> $a['content']['time'];
        });

        $slowQueries = array_slice($slowQueries, 0, $limit);

        return $this->formatter->format([
            'slow_queries' => array_map(function ($query) {
                return [
                    'id' => $query['id'],
                    'sql' => $query['content']['sql'] ?? '',
                    'time' => $query['content']['time'] ?? 0,
                    'connection' => $query['content']['connection'] ?? '',
                    'created_at' => $query['created_at'] ?? '',
                ];
            }, $slowQueries),
            'threshold_ms' => $threshold,
            'total_slow' => count($slowQueries),
        ], 'standard');
    }

    /**
     * Get duplicate/repeated queries (potential N+1).
     */
    protected function getDuplicateQueries(array $arguments): array
    {
        $limit = $this->pagination->getLimit($arguments['limit'] ?? 10);
        $entries = $this->normalizeEntries($this->getEntries($arguments));

        $queryGroups = [];

        foreach ($entries as $entry) {
            $sql = $entry['content']['sql'] ?? '';
            if (empty($sql)) {
                continue;
            }

            // Normalize SQL to detect duplicates (remove literal values)
            $normalized = $this->normalizeSql($sql);

            if (! isset($queryGroups[$normalized])) {
                $queryGroups[$normalized] = [
                    'sql' => $sql,
                    'count' => 0,
                    'total_time' => 0,
                    'avg_time' => 0,
                ];
            }

            $queryGroups[$normalized]['count']++;
            $queryGroups[$normalized]['total_time'] += $entry['content']['time'] ?? 0;
        }

        // Calculate averages and filter duplicates
        $duplicates = [];
        foreach ($queryGroups as $key => $group) {
            if ($group['count'] > 1) {
                $group['avg_time'] = $group['total_time'] / $group['count'];
                $duplicates[] = $group;
            }
        }

        // Sort by count (most frequent first)
        usort($duplicates, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        $duplicates = array_slice($duplicates, 0, $limit);

        return $this->formatter->format([
            'duplicate_queries' => $duplicates,
            'total_duplicates' => count($duplicates),
            'note' => 'Duplicate queries may indicate N+1 query problems',
        ], 'standard');
    }

    /**
     * Normalize SQL for duplicate detection.
     */
    protected function normalizeSql(string $sql): string
    {
        // Replace numbers and quoted strings with placeholders
        $normalized = preg_replace('/\d+/', '?', $sql);
        $normalized = preg_replace("/'[^']*'/", '?', $normalized);
        $normalized = preg_replace('/"[^"]*"/', '?', $normalized);

        return $normalized;
    }

    /**
     * Get fields to include in list view.
     */
    protected function getListFields(): array
    {
        return [
            'id',
            'content.sql',
            'content.time',
            'content.connection',
            'created_at',
        ];
    }

    /**
     * Get searchable fields.
     */
    protected function getSearchableFields(): array
    {
        return [
            'sql',
            'connection',
        ];
    }

    /**
     * Override stats to include query-specific metrics.
     */
    public function stats(array $arguments = []): array
    {
        $entries = $this->normalizeEntries($this->getEntries($arguments));

        if (empty($entries)) {
            return $this->formatter->formatStats([]);
        }

        $times = array_map(fn ($e) => $e['content']['time'] ?? 0, $entries);
        $connections = [];

        foreach ($entries as $entry) {
            $connection = $entry['content']['connection'] ?? 'unknown';
            $connections[$connection] = ($connections[$connection] ?? 0) + 1;
        }

        $slowThreshold = $this->config['slow_query_ms'] ?? 100;
        $slowCount = count(array_filter($entries, function ($entry) use ($slowThreshold) {
            return ($entry['content']['time'] ?? 0) >= $slowThreshold;
        }));

        return $this->formatter->formatStats([
            'total_queries' => count($entries),
            'time' => [
                'avg' => array_sum($times) / count($times),
                'min' => min($times),
                'max' => max($times),
                'total' => array_sum($times),
                'p50' => $this->percentile($times, 50),
                'p95' => $this->percentile($times, 95),
                'p99' => $this->percentile($times, 99),
            ],
            'connections' => $connections,
            'slow_queries' => [
                'count' => $slowCount,
                'threshold_ms' => $slowThreshold,
                'percentage' => round(($slowCount / count($entries)) * 100, 2).'%',
            ],
        ]);
    }
}
