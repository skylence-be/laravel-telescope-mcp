<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\Install\CodeEnvironment;

use Illuminate\Support\Facades\Process;
use Skylence\TelescopeMcp\Contracts\McpClient;
use Skylence\TelescopeMcp\Install\Detection\DetectionStrategyFactory;
use Skylence\TelescopeMcp\Install\Enums\McpInstallationStrategy;
use Skylence\TelescopeMcp\Install\Enums\Platform;
use Skylence\TelescopeMcp\Install\Mcp\FileWriter;

abstract class CodeEnvironment
{
    public bool $useAbsolutePathForMcp = false;

    public function __construct(protected readonly DetectionStrategyFactory $strategyFactory) {}

    abstract public function name(): string;

    abstract public function displayName(): string;

    public function mcpClientName(): ?string
    {
        return $this->displayName();
    }

    public function useAbsolutePathForMcp(): bool
    {
        return $this->useAbsolutePathForMcp;
    }

    public function getPhpPath(bool $forceAbsolutePath = false): string
    {
        return ($this->useAbsolutePathForMcp() || $forceAbsolutePath) ? PHP_BINARY : 'php';
    }

    public function getArtisanPath(bool $forceAbsolutePath = false): string
    {
        return ($this->useAbsolutePathForMcp() || $forceAbsolutePath) ? base_path('artisan') : 'artisan';
    }

    /**
     * @return array{paths?: string[], command?: string, files?: string[]}
     */
    abstract public function systemDetectionConfig(Platform $platform): array;

    /**
     * @return array{paths?: string[], files?: string[]}
     */
    abstract public function projectDetectionConfig(): array;

    public function detectOnSystem(Platform $platform): bool
    {
        $config = $this->systemDetectionConfig($platform);
        $strategy = $this->strategyFactory->makeFromConfig($config);

        return $strategy->detect($config, $platform);
    }

    public function detectInProject(string $basePath): bool
    {
        $config = array_merge($this->projectDetectionConfig(), ['basePath' => $basePath]);
        $strategy = $this->strategyFactory->makeFromConfig($config);

        return $strategy->detect($config);
    }

    public function isMcpClient(): bool
    {
        return $this->mcpClientName() && $this instanceof McpClient;
    }

    public function mcpInstallationStrategy(): McpInstallationStrategy
    {
        return McpInstallationStrategy::FILE;
    }

    public function shellMcpCommand(): ?string
    {
        return null;
    }

    public function mcpConfigPath(): ?string
    {
        return null;
    }

    public function mcpConfigKey(): string
    {
        return 'mcpServers';
    }

    /**
     * @param  array<int, string>  $args
     * @param  array<string, string>  $env
     */
    public function installMcp(string $key, string $command, array $args = [], array $env = []): bool
    {
        return match ($this->mcpInstallationStrategy()) {
            McpInstallationStrategy::SHELL => $this->installShellMcp($key, $command, $args, $env),
            McpInstallationStrategy::FILE => $this->installFileMcp($key, $command, $args, $env),
            McpInstallationStrategy::NONE => false,
        };
    }

    /**
     * @param  array<int, string>  $args
     * @param  array<string, string>  $env
     */
    protected function installShellMcp(string $key, string $command, array $args = [], array $env = []): bool
    {
        $shellCommand = $this->shellMcpCommand();
        if ($shellCommand === null) {
            return false;
        }

        $envString = '';
        foreach ($env as $envKey => $value) {
            $envKey = strtoupper($envKey);
            $envString .= "-e {$envKey}=\"{$value}\" ";
        }

        $command = str_replace([
            '{key}', '{command}', '{args}', '{env}',
        ], [
            $key,
            $command,
            implode(' ', array_map(fn (string $arg): string => '"'.$arg.'"', $args)),
            trim($envString),
        ], $shellCommand);

        $result = Process::run($command);
        if ($result->successful()) {
            return true;
        }

        return str_contains($result->errorOutput(), 'already exists');
    }

    /**
     * @param  array<int, string>  $args
     * @param  array<string, string>  $env
     */
    protected function installFileMcp(string $key, string $command, array $args = [], array $env = []): bool
    {
        $path = $this->mcpConfigPath();
        if (! $path) {
            return false;
        }

        return (new FileWriter($path))
            ->configKey($this->mcpConfigKey())
            ->addServer($key, $command, $args, $env)
            ->save();
    }
}
