<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

final class LogsTool extends TelescopeAbstractTool
{
    protected string $entryType = 'log';

    public function getShortName(): string
    {
        return 'logs';
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getShortName(),
            'description' => 'View and manage application logs from Telescope',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['summary', 'list', 'detail', 'stats', 'search', 'prune'],
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
                    'level' => [
                        'type' => 'string',
                        'enum' => ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
                        'description' => 'Filter by log level',
                    ],
                    'older_than' => [
                        'type' => 'string',
                        'description' => 'Prune entries older than this period (e.g., "7d", "30d")',
                        'default' => '30d',
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
            'prune' => $this->pruneLogs($arguments),
            default => parent::execute($arguments),
        };
    }

    /**
     * Prune old log entries.
     */
    protected function pruneLogs(array $arguments): array
    {
        try {
            $period = $arguments['older_than'] ?? '30d';
            $cutoffTime = $this->getPeriodCutoffTime($period);

            // Get all log entries
            $allEntries = $this->getEntries(['limit' => 100000]);

            $entriesToDelete = [];
            foreach ($allEntries as $entry) {
                $createdAt = $entry->createdAt ?? null;
                if ($createdAt) {
                    $entryTimestamp = method_exists($createdAt, 'timestamp')
                        ? $createdAt->timestamp
                        : strtotime((string) $createdAt);

                    if ($entryTimestamp < $cutoffTime) {
                        $entriesToDelete[] = $entry->uuid ?? $entry->id;
                    }
                }
            }

            // Delete entries using storage
            $deletedCount = 0;
            foreach ($entriesToDelete as $uuid) {
                try {
                    \DB::table('telescope_entries')
                        ->where('uuid', $uuid)
                        ->delete();
                    $deletedCount++;
                } catch (\Exception $e) {
                    // Continue deleting others
                }
            }

            return $this->formatResponse(
                json_encode([
                    'success' => true,
                    'message' => "Pruned {$deletedCount} log entries older than {$period}",
                    'deleted_count' => $deletedCount,
                    'period' => $period,
                    'cutoff_timestamp' => $cutoffTime,
                ], JSON_PRETTY_PRINT)
            );
        } catch (\Exception $e) {
            return $this->formatError("Failed to prune logs: {$e->getMessage()}");
        }
    }

    /**
     * Get fields to include in list view.
     */
    protected function getListFields(): array
    {
        return [
            'id',
            'content.level',
            'content.message',
            'content.context',
            'created_at',
        ];
    }

    /**
     * Get searchable fields.
     */
    protected function getSearchableFields(): array
    {
        return [
            'level',
            'message',
        ];
    }

    /**
     * Override stats to include log-specific metrics.
     */
    public function stats(array $arguments = []): array
    {
        $entries = $this->normalizeEntries($this->getEntries($arguments));

        if (empty($entries)) {
            return $this->formatter->formatStats([]);
        }

        // Count by log level
        $levelCounts = [];
        foreach ($entries as $entry) {
            $level = $entry['content']['level'] ?? 'unknown';
            $levelCounts[$level] = ($levelCounts[$level] ?? 0) + 1;
        }

        return $this->formatter->formatStats([
            'total_logs' => count($entries),
            'levels' => $levelCounts,
            'critical_count' => ($levelCounts['critical'] ?? 0) + ($levelCounts['emergency'] ?? 0) + ($levelCounts['alert'] ?? 0),
            'error_count' => $levelCounts['error'] ?? 0,
            'warning_count' => $levelCounts['warning'] ?? 0,
            'info_count' => $levelCounts['info'] ?? 0,
            'debug_count' => $levelCounts['debug'] ?? 0,
        ]);
    }
}
