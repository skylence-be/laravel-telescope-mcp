<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools\Adapters;

use Skylence\TelescopeMcp\MCP\Tools\AbstractTool;
use Skylence\TelescopeMcp\MCP\Tools\MaintenanceTool;

final class MaintenanceToolAdapter extends AbstractToolAdapter
{
    protected function getTool(): AbstractTool
    {
        return new MaintenanceTool();
    }
}
