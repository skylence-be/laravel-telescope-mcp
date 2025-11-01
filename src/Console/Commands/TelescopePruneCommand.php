<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TelescopePruneCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'telescope-mcp:prune
                            {--hours= : Prune entries older than this many hours}
                            {--type= : Entry type to prune (request, log, query, etc.)}
                            {--force : Force the operation without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Prune old Telescope entries from the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = $this->option('hours') ?? 168; // Default 7 days
        $type = $this->option('type');
        $force = $this->option('force');

        // Confirm destructive operation
        if (! $force && ! $this->confirm(
            "This will delete Telescope entries older than {$hours} hours" .
            ($type ? " of type '{$type}'" : '') .
            ". Continue?"
        )) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        try {
            $cutoffTime = now()->subHours($hours);

            $this->info("Pruning Telescope entries older than {$cutoffTime}...");

            $query = DB::table('telescope_entries')
                ->where('created_at', '<', $cutoffTime);

            if ($type) {
                $query->where('type', $type);
            }

            $count = $query->count();

            if ($count === 0) {
                $this->info('No entries to prune.');

                return self::SUCCESS;
            }

            $this->info("Found {$count} entries to delete...");

            $deleted = $query->delete();

            $this->info("✓ Successfully pruned {$deleted} Telescope entries.");

            // Log the operation
            if (config('telescope-mcp.logging.enabled', true)) {
                \Log::channel(config('telescope-mcp.logging.access_channel'))
                    ->info('Telescope entries pruned via artisan command', [
                        'hours' => $hours,
                        'type' => $type ?? 'all',
                        'deleted' => $deleted,
                    ]);
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to prune entries: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
