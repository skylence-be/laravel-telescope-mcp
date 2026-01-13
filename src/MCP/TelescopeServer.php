<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP;

use Laravel\Mcp\Server;
use Skylence\TelescopeMcp\MCP\Tools\Adapters\CacheToolAdapter;
use Skylence\TelescopeMcp\MCP\Tools\Adapters\CommandsToolAdapter;
use Skylence\TelescopeMcp\MCP\Tools\Adapters\EventsToolAdapter;
use Skylence\TelescopeMcp\MCP\Tools\Adapters\ExceptionsToolAdapter;
use Skylence\TelescopeMcp\MCP\Tools\Adapters\GatesToolAdapter;
use Skylence\TelescopeMcp\MCP\Tools\Adapters\JobsToolAdapter;
use Skylence\TelescopeMcp\MCP\Tools\Adapters\LogsToolAdapter;
use Skylence\TelescopeMcp\MCP\Tools\Adapters\MaintenanceToolAdapter;
use Skylence\TelescopeMcp\MCP\Tools\Adapters\ModelsToolAdapter;
use Skylence\TelescopeMcp\MCP\Tools\Adapters\NotificationsToolAdapter;
use Skylence\TelescopeMcp\MCP\Tools\Adapters\OverviewToolAdapter;
use Skylence\TelescopeMcp\MCP\Tools\Adapters\QueriesToolAdapter;
use Skylence\TelescopeMcp\MCP\Tools\Adapters\RedisToolAdapter;
use Skylence\TelescopeMcp\MCP\Tools\Adapters\RequestsToolAdapter;
use Skylence\TelescopeMcp\MCP\Tools\Adapters\ScheduleToolAdapter;
use Skylence\TelescopeMcp\MCP\Tools\Adapters\ViewsToolAdapter;

/**
 * Laravel MCP Server for Telescope Tools
 *
 * This server provides stdio MCP access to Telescope monitoring tools
 * via Laravel's official MCP package. It adapts the existing HTTP-based
 * tools to work with Laravel's MCP Tool interface.
 */
final class TelescopeServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Laravel Telescope MCP';

    /**
     * The MCP server's version.
     */
    protected string $version = '1.0.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = 'Laravel Telescope MCP server providing monitoring and debugging tools for Laravel applications via Model Context Protocol.';

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [];

    /**
     * Bootstrap the server.
     */
    protected function boot(): void
    {
        // Only register tools if Telescope is installed
        if (! class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            return;
        }

        // Register maintenance tool (always available)
        $this->tools[] = MaintenanceToolAdapter::class;

        // Register all Telescope monitoring tools
        try {
            $this->tools = array_merge($this->tools, [
                OverviewToolAdapter::class,
                RequestsToolAdapter::class,
                LogsToolAdapter::class,
                ExceptionsToolAdapter::class,
                QueriesToolAdapter::class,
                CommandsToolAdapter::class,
                ScheduleToolAdapter::class,
                JobsToolAdapter::class,
                CacheToolAdapter::class,
                EventsToolAdapter::class,
                GatesToolAdapter::class,
                ModelsToolAdapter::class,
                NotificationsToolAdapter::class,
                RedisToolAdapter::class,
                ViewsToolAdapter::class,
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail - Telescope might not be configured
            if (config('telescope-mcp.logging.enabled', true)) {
                \Illuminate\Support\Facades\Log::channel(
                    config('telescope-mcp.logging.error_channel', 'telescope-mcp-error')
                )->warning('Failed to register some Telescope tools', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
