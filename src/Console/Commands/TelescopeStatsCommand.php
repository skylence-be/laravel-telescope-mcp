<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\Console\Commands;

use Illuminate\Console\Command;
use Skylence\TelescopeMcp\Support\ResolvesTelescopeConnection;

class TelescopeStatsCommand extends Command
{
    use ResolvesTelescopeConnection;

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'telescope-mcp:stats
                            {--json : Output as JSON}';

    /**
     * The console command description.
     */
    protected $description = 'Display Telescope database statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $stats = $this->getStats();

            if ($this->option('json')) {
                $this->line(json_encode($stats, JSON_PRETTY_PRINT));

                return self::SUCCESS;
            }

            $this->displayStats($stats);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to get statistics: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Get Telescope statistics.
     */
    protected function getStats(): array
    {
        $totalEntries = $this->telescopeTable()->count();

        $typeCounts = $this->telescopeTable()
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'type')
            ->toArray();

        $oldestEntry = $this->telescopeTable()
            ->orderBy('created_at', 'asc')
            ->value('created_at');

        $newestEntry = $this->telescopeTable()
            ->orderBy('created_at', 'desc')
            ->value('created_at');

        // Calculate database size (approximation)
        $tableSize = $this->getTableSize();

        return [
            'total_entries' => $totalEntries,
            'by_type' => $typeCounts,
            'oldest_entry' => $oldestEntry,
            'newest_entry' => $newestEntry,
            'table_size' => $tableSize,
        ];
    }

    /**
     * Display statistics in a formatted table.
     */
    protected function displayStats(array $stats): void
    {
        $this->newLine();
        $this->info('📊 Telescope Database Statistics');
        $this->newLine();

        // Overall stats
        $this->line("Total Entries: <fg=green>{$stats['total_entries']}</>");
        $this->line("Oldest Entry:  <fg=yellow>{$stats['oldest_entry']}</>");
        $this->line("Newest Entry:  <fg=yellow>{$stats['newest_entry']}</>");

        if ($stats['table_size']) {
            $this->line("Database Size: <fg=cyan>{$stats['table_size']}</>");
        }

        $this->newLine();

        // Entries by type
        if (! empty($stats['by_type'])) {
            $this->info('Entries by Type:');
            $this->newLine();

            $tableData = [];
            foreach ($stats['by_type'] as $type => $count) {
                $percentage = $stats['total_entries'] > 0
                    ? round(($count / $stats['total_entries']) * 100, 1)
                    : 0;

                $tableData[] = [
                    'type' => $type,
                    'count' => number_format($count),
                    'percentage' => $percentage.'%',
                ];
            }

            $this->table(['Type', 'Count', 'Percentage'], $tableData);
        }

        $this->newLine();
    }

    /**
     * Get approximate table size.
     */
    protected function getTableSize(): ?string
    {
        // For file-based data source, return the file size
        if ($this->isFileDataSource()) {
            $filePath = config('telescope-mcp.file_source.path');
            if ($filePath && file_exists($filePath)) {
                $bytes = filesize($filePath);

                return $this->formatBytes($bytes);
            }

            return null;
        }

        try {
            $connection = $this->getTelescopeConnectionName();
            $db = $connection ? \DB::connection($connection) : \DB::connection();

            // MySQL/MariaDB
            $size = $db->select(
                "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
                AND table_name = 'telescope_entries'"
            );

            if (! empty($size)) {
                return $size[0]->size_mb.' MB';
            }
        } catch (\Exception $e) {
            // Silently fail if not MySQL or query doesn't work
        }

        return null;
    }

    protected function formatBytes(int|false $bytes): string
    {
        if ($bytes === false || $bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
