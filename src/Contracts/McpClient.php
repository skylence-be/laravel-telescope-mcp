<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\Contracts;

interface McpClient
{
    public function name(): string;

    public function mcpClientName(): ?string;

    public function useAbsolutePathForMcp(): bool;

    public function getPhpPath(bool $forceAbsolutePath = false): string;

    public function getArtisanPath(bool $forceAbsolutePath = false): string;

    /**
     * @param  array<int, string>  $args
     * @param  array<string, string>  $env
     */
    public function installMcp(string $key, string $command, array $args = [], array $env = []): bool;
}
