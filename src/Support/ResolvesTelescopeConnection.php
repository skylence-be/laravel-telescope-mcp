<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait ResolvesTelescopeConnection
{
    /**
     * Get the telescope database connection name.
     *
     * Returns the MCP SQLite connection when using file data source,
     * otherwise returns the configured Telescope connection.
     */
    protected function getTelescopeConnectionName(): ?string
    {
        if ($this->isFileDataSource()) {
            return 'telescope_mcp_sqlite';
        }

        return config('telescope.storage.database.connection');
    }

    /**
     * Get a query builder for a telescope table using the resolved connection.
     */
    protected function telescopeTable(string $table = 'telescope_entries'): Builder
    {
        $connection = $this->getTelescopeConnectionName();

        if ($connection) {
            return DB::connection($connection)->table($table);
        }

        return DB::table($table);
    }

    /**
     * Check if the current data source is a file (SQLite export).
     */
    protected function isFileDataSource(): bool
    {
        return config('telescope-mcp.data_source', 'live') === 'file'
            && config('telescope-mcp.file_source.path') !== null;
    }
}
