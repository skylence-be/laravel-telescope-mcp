<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TelescopeClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'telescope-mcp:clear
                            {--type= : Entry type to clear (request, log, query, etc.)}
                            {--force : Force the operation without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Clear all Telescope entries or entries of a specific type';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->option('type');
        $force = $this->option('force');

        // Confirm destructive operation
        $message = $type
            ? "This will delete ALL Telescope entries of type '{$type}'. Continue?"
            : "This will delete ALL Telescope entries. Continue?";

        if (! $force && ! $this->confirm($message)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        try {
            $query = DB::table('telescope_entries');

            if ($type) {
                $query->where('type', $type);
            }

            $count = $query->count();

            if ($count === 0) {
                $this->info('No entries to clear.');

                return self::SUCCESS;
            }

            $this->warn("Clearing {$count} Telescope entries...");

            $deleted = $query->delete();

            $this->info("✓ Successfully cleared {$deleted} Telescope entries.");

            // Log the operation
            if (config('telescope-mcp.logging.enabled', true)) {
                \Log::channel(config('telescope-mcp.logging.access_channel'))
                    ->info('Telescope entries cleared via artisan command', [
                        'type' => $type ?? 'all',
                        'deleted' => $deleted,
                    ]);
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to clear entries: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
