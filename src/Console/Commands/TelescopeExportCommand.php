<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Skylence\TelescopeMcp\Support\ResolvesTelescopeConnection;

class TelescopeExportCommand extends Command
{
    use ResolvesTelescopeConnection;

    protected $signature = 'telescope-mcp:export
                            {--format=sqlite : Export format (sqlite or jsonl)}
                            {--period=7d : Time period (1h, 6h, 24h, 7d, 14d, 30d, 90d)}
                            {--type=* : Entry types to export (empty = all)}
                            {--output= : Output file path}
                            {--chunk=500 : Batch size for SQLite inserts}';

    protected $description = 'Export Telescope data to SQLite or JSONL for offline MCP access';

    public function handle(): int
    {
        $format = $this->option('format');
        $period = $this->option('period');
        $types = array_filter($this->option('type'));
        $chunk = (int) $this->option('chunk') ?: 500;

        $outputPath = $this->option('output') ?? $this->defaultOutputPath($format);
        $cutoff = $this->calculateCutoff($period);

        $sourceConnection = config('telescope.storage.database.connection');

        $this->info("Exporting Telescope data ({$period}) to {$format}: {$outputPath}");
        $this->info('Source connection: ' . ($sourceConnection ?? 'default'));
        $this->info('Cutoff: ' . $cutoff);
        $this->newLine();

        return match ($format) {
            'sqlite' => $this->exportSqlite($sourceConnection, $outputPath, $cutoff, $types, $chunk),
            'jsonl' => $this->exportJsonl($sourceConnection, $outputPath, $cutoff, $types),
            default => $this->invalidFormat($format),
        };
    }

    protected function exportSqlite(?string $sourceConnection, string $outputPath, string $cutoff, array $types, int $chunk): int
    {
        // Delete existing file
        if (file_exists($outputPath)) {
            unlink($outputPath);
        }

        // Ensure directory exists
        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Touch file so SQLite can open it
        touch($outputPath);

        // Register temporary SQLite connection
        Config::set('database.connections.telescope_mcp_export', [
            'driver' => 'sqlite',
            'database' => $outputPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        // Create schema
        $this->createSqliteSchema();

        // Build source query
        $sourceQuery = $this->buildSourceQuery($sourceConnection, 'telescope_entries', $cutoff, $types);
        $totalEntries = (clone $sourceQuery)->count();

        if ($totalEntries === 0) {
            $this->warn('No entries found for the specified period.');

            return self::SUCCESS;
        }

        $this->info("Found {$totalEntries} entries to export.");

        // Export entries using cursor + batch insert
        $bar = $this->output->createProgressBar($totalEntries);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%');

        $batch = [];
        $exportedCount = 0;
        $uuids = [];

        foreach ($sourceQuery->orderBy('sequence', 'asc')->cursor() as $row) {
            $batch[] = (array) $row;
            $uuids[] = $row->uuid;

            if (count($batch) >= $chunk) {
                DB::connection('telescope_mcp_export')->table('telescope_entries')->insert($batch);
                $exportedCount += count($batch);
                $bar->advance(count($batch));
                $batch = [];
            }

            // Export tags in batches of UUIDs
            if (count($uuids) >= $chunk) {
                $this->exportTagsBatch($sourceConnection, $uuids);
                $uuids = [];
            }
        }

        // Flush remaining entries
        if (! empty($batch)) {
            DB::connection('telescope_mcp_export')->table('telescope_entries')->insert($batch);
            $exportedCount += count($batch);
            $bar->advance(count($batch));
        }

        // Flush remaining tags
        if (! empty($uuids)) {
            $this->exportTagsBatch($sourceConnection, $uuids);
        }

        $bar->finish();
        $this->newLine(2);

        // Export monitoring table
        $monitoringCount = $this->exportMonitoring($sourceConnection);

        // Add indexes after data is loaded (faster than during insert)
        $this->addSqliteIndexes();

        // Disconnect and show stats
        DB::purge('telescope_mcp_export');

        $fileSize = $this->formatFileSize(filesize($outputPath));

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Entries exported', number_format($exportedCount)],
                ['Monitoring tags', number_format($monitoringCount)],
                ['File size', $fileSize],
                ['Output', $outputPath],
                ['Format', 'SQLite'],
            ]
        );

        $this->newLine();
        $this->info('To use this export, set these environment variables:');
        $this->line("  TELESCOPE_MCP_DATA_SOURCE=file");
        $this->line("  TELESCOPE_MCP_FILE_PATH={$outputPath}");

        return self::SUCCESS;
    }

    protected function exportJsonl(?string $sourceConnection, string $outputPath, string $cutoff, array $types): int
    {
        // Ensure directory exists
        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $sourceQuery = $this->buildSourceQuery($sourceConnection, 'telescope_entries', $cutoff, $types);
        $totalEntries = (clone $sourceQuery)->count();

        if ($totalEntries === 0) {
            $this->warn('No entries found for the specified period.');

            return self::SUCCESS;
        }

        $this->info("Found {$totalEntries} entries to export.");

        $bar = $this->output->createProgressBar($totalEntries);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%');

        $handle = fopen($outputPath, 'w');
        $exportedCount = 0;

        foreach ($sourceQuery->orderBy('sequence', 'asc')->cursor() as $row) {
            $data = (array) $row;

            // Decode content JSON for clean output
            if (isset($data['content']) && is_string($data['content'])) {
                $decoded = json_decode($data['content'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data['content'] = $decoded;
                }
            }

            fwrite($handle, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
            $exportedCount++;
            $bar->advance();
        }

        fclose($handle);
        $bar->finish();
        $this->newLine(2);

        $fileSize = $this->formatFileSize(filesize($outputPath));

        $this->table(
            ['Metric', 'Value'],
            [
                ['Entries exported', number_format($exportedCount)],
                ['File size', $fileSize],
                ['Output', $outputPath],
                ['Format', 'JSONL'],
            ]
        );

        return self::SUCCESS;
    }

    protected function createSqliteSchema(): void
    {
        $schema = Schema::connection('telescope_mcp_export');

        $schema->create('telescope_entries', function ($table) {
            $table->bigIncrements('sequence');
            $table->string('uuid', 36);
            $table->string('batch_id', 36);
            $table->string('family_hash', 36)->nullable();
            $table->boolean('should_display_on_index')->default(true);
            $table->string('type', 20);
            $table->longText('content');
            $table->dateTime('created_at')->nullable();
        });

        $schema->create('telescope_entries_tags', function ($table) {
            $table->string('entry_uuid', 36);
            $table->string('tag');
        });

        $schema->create('telescope_monitoring', function ($table) {
            $table->string('tag');
        });
    }

    protected function addSqliteIndexes(): void
    {
        $this->info('Adding indexes...');

        $db = DB::connection('telescope_mcp_export');

        $db->statement('CREATE UNIQUE INDEX IF NOT EXISTS telescope_entries_uuid_unique ON telescope_entries (uuid)');
        $db->statement('CREATE INDEX IF NOT EXISTS telescope_entries_batch_id_index ON telescope_entries (batch_id)');
        $db->statement('CREATE INDEX IF NOT EXISTS telescope_entries_family_hash_index ON telescope_entries (family_hash)');
        $db->statement('CREATE INDEX IF NOT EXISTS telescope_entries_created_at_index ON telescope_entries (created_at)');
        $db->statement('CREATE INDEX IF NOT EXISTS telescope_entries_type_should_display_on_index_index ON telescope_entries (type, should_display_on_index)');

        $db->statement('CREATE INDEX IF NOT EXISTS telescope_entries_tags_entry_uuid_tag_index ON telescope_entries_tags (entry_uuid, tag)');
        $db->statement('CREATE INDEX IF NOT EXISTS telescope_entries_tags_tag_index ON telescope_entries_tags (tag)');
    }

    protected function exportTagsBatch(?string $sourceConnection, array $uuids): void
    {
        $query = $sourceConnection
            ? DB::connection($sourceConnection)->table('telescope_entries_tags')
            : DB::table('telescope_entries_tags');

        $tags = $query->whereIn('entry_uuid', $uuids)->get();

        if ($tags->isEmpty()) {
            return;
        }

        $tagBatch = $tags->map(fn ($row) => (array) $row)->toArray();

        DB::connection('telescope_mcp_export')->table('telescope_entries_tags')->insert($tagBatch);
    }

    protected function exportMonitoring(?string $sourceConnection): int
    {
        $query = $sourceConnection
            ? DB::connection($sourceConnection)->table('telescope_monitoring')
            : DB::table('telescope_monitoring');

        try {
            $rows = $query->get();
        } catch (\Exception $e) {
            // Table may not exist
            return 0;
        }

        if ($rows->isEmpty()) {
            return 0;
        }

        $data = $rows->map(fn ($row) => (array) $row)->toArray();
        DB::connection('telescope_mcp_export')->table('telescope_monitoring')->insert($data);

        return count($data);
    }

    protected function buildSourceQuery(?string $sourceConnection, string $table, string $cutoff, array $types): \Illuminate\Database\Query\Builder
    {
        $query = $sourceConnection
            ? DB::connection($sourceConnection)->table($table)
            : DB::table($table);

        $query->where('created_at', '>=', $cutoff);

        if (! empty($types)) {
            $query->whereIn('type', $types);
        }

        return $query;
    }

    protected function calculateCutoff(string $period): string
    {
        $seconds = match ($period) {
            '1h' => 60 * 60,
            '6h' => 6 * 60 * 60,
            '24h' => 24 * 60 * 60,
            '7d' => 7 * 24 * 60 * 60,
            '14d' => 14 * 24 * 60 * 60,
            '30d' => 30 * 24 * 60 * 60,
            '90d' => 90 * 24 * 60 * 60,
            default => 7 * 24 * 60 * 60,
        };

        return date('Y-m-d H:i:s', time() - $seconds);
    }

    protected function defaultOutputPath(string $format): string
    {
        $extension = $format === 'jsonl' ? 'jsonl' : 'sqlite';

        return storage_path("telescope-export.{$extension}");
    }

    protected function formatFileSize(int|false $bytes): string
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

        return round($bytes, 2) . ' ' . $units[$i];
    }

    protected function invalidFormat(string $format): int
    {
        $this->error("Invalid format: {$format}. Supported: sqlite, jsonl");

        return self::FAILURE;
    }
}
