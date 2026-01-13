<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools\Adapters;

use Skylence\TelescopeMcp\MCP\Tools\AbstractTool;
use Skylence\TelescopeMcp\MCP\Tools${tool}Tool;
use Skylence\TelescopeMcp\Services\PaginationManager;
use Skylence\TelescopeMcp\Services\ResponseFormatter;

final class CacheToolAdapter extends AbstractToolAdapter
{
    protected function getTool(): AbstractTool
    {
        return new CacheTool(
            config('telescope-mcp', []),
            app(PaginationManager::class),
            app(ResponseFormatter::class)
        );
    }
}
