<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools\Adapters;

use Skylence\TelescopeMcp\MCP\Tools\AbstractTool;
use Skylence\TelescopeMcp\MCP\Tools\ModelsTool;
use Skylence\TelescopeMcp\Services\PaginationManager;
use Skylence\TelescopeMcp\Services\ResponseFormatter;

final class ModelsToolAdapter extends AbstractToolAdapter
{
    protected function getTool(): AbstractTool
    {
        return new ModelsTool(
            config('telescope-mcp', []),
            app(PaginationManager::class),
            app(ResponseFormatter::class)
        );
    }
}
