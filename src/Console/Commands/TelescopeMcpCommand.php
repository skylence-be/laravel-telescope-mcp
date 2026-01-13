<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('telescope:mcp', 'Starts Laravel Telescope MCP (usually from mcp.json)')]
class TelescopeMcpCommand extends Command
{
    public function handle(): int
    {
        return Artisan::call('mcp:start telescope');
    }
}
