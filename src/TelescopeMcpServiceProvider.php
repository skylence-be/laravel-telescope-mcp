<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Skylence\TelescopeMcp\Http\Middleware\AuthenticateMcp;
use Skylence\TelescopeMcp\MCP\TelescopeMcpServer;
use Skylence\TelescopeMcp\Services\PaginationManager;
use Skylence\TelescopeMcp\Services\ResponseFormatter;
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

        // Register Logger as singleton
        $this->app->singleton(Logger::class, function ($app) {
            return new Logger(
                config('telescope-mcp.logging.enabled', true),
                config('telescope-mcp.logging.channel', 'stack')
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

        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/telescope-mcp.php' => config_path('telescope-mcp.php'),
        ], 'telescope-mcp-config');

        // Register middleware
        $this->app['router']->aliasMiddleware('telescope-mcp.auth', AuthenticateMcp::class);

        // Register routes
        $this->registerRoutes();
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        $middleware = config('telescope-mcp.middleware', ['api']);

        // Add auth middleware if enabled
        if (config('telescope-mcp.auth.enabled', true)) {
            $middleware[] = 'telescope-mcp.auth';
        }

        Route::group([
            'prefix' => config('telescope-mcp.path', 'telescope-mcp'),
            'middleware' => $middleware,
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });
    }
}
