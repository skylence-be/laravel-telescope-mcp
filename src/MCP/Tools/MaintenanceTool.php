<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

use Illuminate\Support\Facades\DB;

final class MaintenanceTool extends AbstractTool
{
    public function getShortName(): string
    {
        return 'maintenance';
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getShortName(),
            'description' => 'Perform maintenance operations on Telescope data',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['prune', 'stats', 'clear'],
                        'description' => 'Maintenance action to perform',
                        'default' => 'stats',
                    ],
                    'older_than' => [
                        'type' => 'string',
                        'enum' => ['1h', '6h', '24h', '7d', '14d', '21d', '30d', '60d', '90d'],
                        'description' => 'Prune entries older than this period',
                        'default' => '30d',
                    ],
                    'entry_type' => [
                        'type' => 'string',
                        'enum' => ['all', 'request', 'command', 'job', 'log', 'query', 'exception', 'cache', 'event', 'mail', 'notification'],
                        'description' => 'Type of entries to prune (for prune action)',
                        'default' => 'all',
                    ],
                    'confirm' => [
                        'type' => 'boolean',
                        'description' => 'Confirmation required for destructive operations',
                        'default' => false,
                    ],
                ],
                'required' => ['action'],
            ],
        ];
    }

    public function execute(array $params): array
    {
        $action = $params['action'] ?? 'stats';

        return match ($action) {
            'prune' => $this->pruneEntries($params),
            'clear' => $this->clearAllEntries($params),
            'stats' => $this->getStorageStats(),
            default => $this->formatError("Unknown action: {$action}"),
        };
    }

    /**
     * Get storage statistics.
     */
    protected function getStorageStats(): array
    {
        try {
            $stats = [
                'total_entries' => DB::table('telescope_entries')->count(),
                'by_type' => [],
                'oldest_entry' => DB::table('telescope_entries')
                    ->orderBy('created_at', 'asc')
                    ->value('created_at'),
                'newest_entry' => DB::table('telescope_entries')
                    ->orderBy('created_at', 'desc')
                    ->value('created_at'),
            ];

            // Count by type
            $typeCounts = DB::table('telescope_entries')
                ->select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->get();

            foreach ($typeCounts as $row) {
                $stats['by_type'][$row->type] = $row->count;
            }

            return $this->formatResponse(
                json_encode($stats, JSON_PRETTY_PRINT),
                $stats
            );
        } catch (\Exception $e) {
            return $this->formatError("Failed to get storage stats: {$e->getMessage()}");
        }
    }

    /**
     * Prune old entries.
     */
    protected function pruneEntries(array $params): array
    {
        if (empty($params['confirm'])) {
            return $this->formatError('Destructive operation requires confirm=true parameter');
        }

        try {
            $olderThan = $params['older_than'] ?? '30d';
            $entryType = $params['entry_type'] ?? 'all';

            $cutoffTime = $this->calculateCutoffTime($olderThan);

            $query = DB::table('telescope_entries')
                ->where('created_at', '<', $cutoffTime);

            if ($entryType !== 'all') {
                $query->where('type', $entryType);
            }

            $count = $query->count();
            $deleted = $query->delete();

            return $this->formatResponse(
                json_encode([
                    'success' => true,
                    'message' => "Pruned {$deleted} entries older than {$olderThan}",
                    'deleted_count' => $deleted,
                    'entry_type' => $entryType,
                    'older_than' => $olderThan,
                    'cutoff_time' => $cutoffTime,
                ], JSON_PRETTY_PRINT)
            );
        } catch (\Exception $e) {
            return $this->formatError("Failed to prune entries: {$e->getMessage()}");
        }
    }

    /**
     * Clear all entries.
     */
    protected function clearAllEntries(array $params): array
    {
        if (empty($params['confirm'])) {
            return $this->formatError('Destructive operation requires confirm=true parameter');
        }

        try {
            $entryType = $params['entry_type'] ?? 'all';

            $query = DB::table('telescope_entries');

            if ($entryType !== 'all') {
                $query->where('type', $entryType);
            }

            $count = $query->count();
            $deleted = $query->delete();

            return $this->formatResponse(
                json_encode([
                    'success' => true,
                    'message' => "Cleared {$deleted} entries",
                    'deleted_count' => $deleted,
                    'entry_type' => $entryType,
                ], JSON_PRETTY_PRINT)
            );
        } catch (\Exception $e) {
            return $this->formatError("Failed to clear entries: {$e->getMessage()}");
        }
    }

    /**
     * Calculate cutoff timestamp from period string.
     */
    protected function calculateCutoffTime(string $period): string
    {
        $now = time();

        $seconds = match ($period) {
            '1h' => 60 * 60,
            '6h' => 6 * 60 * 60,
            '24h' => 24 * 60 * 60,
            '7d' => 7 * 24 * 60 * 60,
            '14d' => 14 * 24 * 60 * 60,
            '21d' => 21 * 24 * 60 * 60,
            '30d' => 30 * 24 * 60 * 60,
            '60d' => 60 * 24 * 60 * 60,
            '90d' => 90 * 24 * 60 * 60,
            default => 30 * 24 * 60 * 60,
        };

        return date('Y-m-d H:i:s', $now - $seconds);
    }
}
