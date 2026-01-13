<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Skylence\TelescopeMcp\Console\Commands\TelescopeClearCommand;
use Skylence\TelescopeMcp\Console\Commands\TelescopeMcpCommand;
use Skylence\TelescopeMcp\Console\Commands\TelescopePruneCommand;
use Skylence\TelescopeMcp\Console\Commands\TelescopeStatsCommand;
use Skylence\TelescopeMcp\Http\Middleware\AuthenticateMcp;
use Skylence\TelescopeMcp\MCP\TelescopeMcpServer;
use Skylence\TelescopeMcp\Services\PaginationManager;
use Skylence\TelescopeMcp\Services\PerformanceAnalyzer;
use Skylence\TelescopeMcp\Services\QueryAnalyzer;
use Skylence\TelescopeMcp\Services\ResponseFormatter;
use Skylence\TelescopeMcp\Services\RouteFilter;
use Skylence\TelescopeMcp\Support\Logger;

final class TelescopeMcpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/telescope-mcp.php',
            'telescope-mcp'
        );

        // Register Logger as singleton with dual channels
        $this->app->singleton(Logger::class, function ($app) {
            return new Logger(
                config('telescope-mcp.logging.enabled', true),
                config('telescope-mcp.logging.access_channel', 'telescope-mcp-access'),
                config('telescope-mcp.logging.error_channel', 'telescope-mcp-error')
            );
        });

        // Register PaginationManager
        $this->app->singleton(PaginationManager::class, function ($app) {
            return new PaginationManager([
                'default' => 10,
                'maximum' => 25,
            ]);
        });

        // Register ResponseFormatter
        $this->app->singleton(ResponseFormatter::class, function ($app) {
            return new ResponseFormatter([
                'summary_threshold' => 5,
            ]);
        });

        // Register PerformanceAnalyzer
        $this->app->singleton(PerformanceAnalyzer::class, function ($app) {
            return new PerformanceAnalyzer(config('telescope-mcp', []));
        });

        // Register QueryAnalyzer
        $this->app->singleton(QueryAnalyzer::class, function ($app) {
            return new QueryAnalyzer(config('telescope-mcp', []));
        });

        // Register RouteFilter
        $this->app->singleton(RouteFilter::class, function ($app) {
            return new RouteFilter();
        });

        // Register TelescopeMcpServer
        $this->app->singleton(TelescopeMcpServer::class, function ($app) {
            return new TelescopeMcpServer();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! config('telescope-mcp.enabled', true)) {
            return;
        }

        // Configure logging channels
        $this->configureLogging();

        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/telescope-mcp.php' => config_path('telescope-mcp.php'),
        ], 'telescope-mcp-config');

        // Register middleware
        $this->app['router']->aliasMiddleware('telescope-mcp.auth', AuthenticateMcp::class);

        // Register stdio MCP routes (Laravel MCP)
        $this->loadRoutesFrom(__DIR__.'/../routes/ai.php');

        // Register HTTP MCP routes
        $this->registerHttpRoutes();

        // Register artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                TelescopeMcpCommand::class,
                TelescopePruneCommand::class,
                TelescopeStatsCommand::class,
                TelescopeClearCommand::class,
            ]);
        }
    }

    /**
     * Configure logging channels for the package.
     */
    protected function configureLogging(): void
    {
        if (! config('telescope-mcp.logging.enabled', true)) {
            return;
        }

        // Add access log channel
        Config::set('logging.channels.telescope-mcp-access', [
            'driver' => 'daily',
            'path' => storage_path('logs/telescope-mcp-access.log'),
            'level' => 'debug',
            'days' => 14,
            'permission' => 0644,
        ]);

        // Add error log channel
        Config::set('logging.channels.telescope-mcp-error', [
            'driver' => 'daily',
            'path' => storage_path('logs/telescope-mcp-error.log'),
            'level' => 'warning',
            'days' => 30,
            'permission' => 0644,
        ]);
    }

    /**
     * Register the HTTP MCP routes.
     */
    protected function registerHttpRoutes(): void
    {
        // Skip HTTP routes if disabled
        if (! config('telescope-mcp.http.enabled', true)) {
            return;
        }

        $middleware = config('telescope-mcp.http.middleware', config('telescope-mcp.middleware', ['api']));

        // Add auth middleware if enabled
        if (config('telescope-mcp.auth.enabled', true)) {
            $middleware[] = 'telescope-mcp.auth';
        }

        Route::group([
            'prefix' => config('telescope-mcp.http.path', config('telescope-mcp.path', 'telescope-mcp')),
            'middleware' => $middleware,
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/http.php');
        });
    }
}
