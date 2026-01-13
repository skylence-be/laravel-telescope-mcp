<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools\Adapters;

use Skylence\TelescopeMcp\MCP\Tools\AbstractTool;
use Skylence\TelescopeMcp\MCP\Tools\OverviewTool;
use Skylence\TelescopeMcp\Services\PaginationManager;
use Skylence\TelescopeMcp\Services\PerformanceAnalyzer;
use Skylence\TelescopeMcp\Services\QueryAnalyzer;
use Skylence\TelescopeMcp\Services\ResponseFormatter;
use Skylence\TelescopeMcp\Services\RouteFilter;

final class OverviewToolAdapter extends AbstractToolAdapter
{
    protected function getTool(): AbstractTool
    {
        return new OverviewTool(
            config('telescope-mcp', []),
            app(PaginationManager::class),
            app(ResponseFormatter::class),
            app(PerformanceAnalyzer::class),
            app(QueryAnalyzer::class),
            app(RouteFilter::class)
        );
    }
}
