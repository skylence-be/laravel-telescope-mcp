<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TelescopeStatsCommand extends Command
{
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
        $totalEntries = DB::table('telescope_entries')->count();

        $typeCounts = DB::table('telescope_entries')
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'type')
            ->toArray();

        $oldestEntry = DB::table('telescope_entries')
            ->orderBy('created_at', 'asc')
            ->value('created_at');

        $newestEntry = DB::table('telescope_entries')
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
        try {
            // MySQL/MariaDB
            $size = DB::select(
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
}
