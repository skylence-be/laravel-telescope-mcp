<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\Support;

use Illuminate\Support\Facades\Log;

final class Logger
{
    /**
     * Create a new logger instance.
     */
    public function __construct(
        private readonly bool $enabled = true,
        private readonly string $accessChannel = 'telescope-mcp-access',
        private readonly string $errorChannel = 'telescope-mcp-error'
    ) {
    }

    /**
     * Log an info message (access log).
     */
    public function info(string $message, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        Log::channel($this->accessChannel)->info($message, $context);
    }

    /**
     * Log a debug message (access log).
     */
    public function debug(string $message, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        Log::channel($this->accessChannel)->debug($message, $context);
    }

    /**
     * Log an error message (error log).
     */
    public function error(string $message, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        Log::channel($this->errorChannel)->error($message, $context);
    }

    /**
     * Log a warning message (error log).
     */
    public function warning(string $message, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        Log::channel($this->errorChannel)->warning($message, $context);
    }
}
