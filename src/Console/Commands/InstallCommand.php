<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Skylence\TelescopeMcp\Contracts\McpClient;
use Skylence\TelescopeMcp\Install\CodeEnvironment\CodeEnvironment;
use Skylence\TelescopeMcp\Install\CodeEnvironmentsDetector;
use Skylence\TelescopeMcp\Install\Mcp\FileWriter;
use Skylence\TelescopeMcp\Support\Config;
use Laravel\Prompts\Concerns\Colors;
use Laravel\Prompts\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;

#[AsCommand('telescope-mcp:install', 'Install Laravel Telescope MCP server into your IDE')]
class InstallCommand extends Command
{
    use Colors;

    private CodeEnvironmentsDetector $codeEnvironmentsDetector;

    private Terminal $terminal;

    /** @var Collection<int, McpClient> */
    private Collection $selectedTargetMcpClient;

    private string $projectName;

    private bool $useSail = false;

    private bool $configureHttpServer = false;

    /** @var array<string> */
    private array $systemInstalledCodeEnvironments = [];

    /** @var array<string> */
    private array $projectInstalledCodeEnvironments = [];

    private string $greenTick;

    private string $redCross;

    public function __construct(protected Config $config)
    {
        parent::__construct();
    }

    public function handle(CodeEnvironmentsDetector $codeEnvironmentsDetector, Terminal $terminal): int
    {
        $this->bootstrap($codeEnvironmentsDetector, $terminal);

        $this->displayHeader();
        $this->discoverEnvironment();
        $this->collectPreferences();
        $this->performInstallation();
        $this->outro();

        return Command::SUCCESS;
    }

    protected function bootstrap(CodeEnvironmentsDetector $codeEnvironmentsDetector, Terminal $terminal): void
    {
        $this->codeEnvironmentsDetector = $codeEnvironmentsDetector;
        $this->terminal = $terminal;
        $this->terminal->initDimensions();

        $this->greenTick = $this->green('✓');
        $this->redCross = $this->red('✗');
        $this->selectedTargetMcpClient = collect();
        $this->projectName = config('app.name', 'Laravel');
    }

    protected function displayHeader(): void
    {
        intro('Laravel Telescope MCP :: Install');
        note(sprintf("Let's configure %s with Telescope MCP", $this->bgYellow($this->black($this->bold($this->projectName)))));
    }

    protected function discoverEnvironment(): void
    {
        $this->systemInstalledCodeEnvironments = $this->codeEnvironmentsDetector->discoverSystemInstalledCodeEnvironments();
        $this->projectInstalledCodeEnvironments = $this->codeEnvironmentsDetector->discoverProjectInstalledCodeEnvironments(base_path());
    }

    protected function collectPreferences(): void
    {
        if ($this->isSailInstalled() && ($this->isRunningInsideSail() || $this->shouldConfigureSail())) {
            $this->useSail = true;
        }

        $this->selectedTargetMcpClient = $this->selectTargetMcpClients();

        if ($this->selectedTargetMcpClient->isNotEmpty()) {
            $this->configureHttpServer = $this->shouldConfigureHttpServer();
        }
    }

    protected function performInstallation(): void
    {
        if ($this->selectedTargetMcpClient->isEmpty()) {
            $this->info('No editors selected for MCP installation.');

            return;
        }

        $this->newLine();
        $this->info(' Installing MCP servers to your selected IDEs');
        $this->newLine();

        usleep(750000);

        $failed = [];
        $longestIdeName = max(
            1,
            ...$this->selectedTargetMcpClient->map(
                fn (McpClient $mcpClient) => Str::length($mcpClient->mcpClientName())
            )->toArray()
        );

        /** @var McpClient $mcpClient */
        foreach ($this->selectedTargetMcpClient as $mcpClient) {
            $ideName = $mcpClient->mcpClientName();
            $ideDisplay = str_pad((string) $ideName, $longestIdeName);
            $this->output->write("  {$ideDisplay}... ");

            $mcp = $this->buildMcpCommand($mcpClient);

            try {
                $result = $this->installMcpServers($mcpClient, $mcp);

                if ($result) {
                    $this->line($this->greenTick);
                } else {
                    $this->line($this->redCross);
                    $failed[$ideName] = 'Failed to write configuration';
                }
            } catch (Exception $e) {
                $this->line($this->redCross);
                $failed[$ideName] = $e->getMessage();
            }
        }

        $this->newLine();

        if ($failed !== []) {
            $this->error(sprintf('%s Some MCP servers failed to install:', $this->redCross));

            foreach ($failed as $ideName => $error) {
                $this->line("  - {$ideName}: {$error}");
            }
        }

        if ($this->configureHttpServer) {
            $this->displayHttpConfigurationInstructions();
        }

        $this->config->setSail($this->useSail);
        $this->config->setEditors(
            $this->selectedTargetMcpClient->map(fn (McpClient $mcpClient): string => $mcpClient->name())->values()->toArray()
        );
    }

    /**
     * @return Collection<int, CodeEnvironment>
     */
    protected function selectTargetMcpClients(): Collection
    {
        $allEnvironments = $this->codeEnvironmentsDetector->getCodeEnvironments();
        $availableEnvironments = $allEnvironments->filter(fn (CodeEnvironment $env): bool => $env instanceof McpClient);

        if ($availableEnvironments->isEmpty()) {
            return collect();
        }

        $options = $availableEnvironments->mapWithKeys(fn (CodeEnvironment $env): array => [$env->name() => $env->displayName()])->sort();

        $installedEnvNames = array_unique(array_merge(
            $this->projectInstalledCodeEnvironments,
            $this->systemInstalledCodeEnvironments
        ));

        $defaults = $this->config->getEditors();
        $detectedDefaults = [];

        if ($defaults === []) {
            foreach ($installedEnvNames as $envKey) {
                $matchingEnv = $availableEnvironments->first(fn (CodeEnvironment $env): bool => strtolower((string) $envKey) === strtolower($env->name()));
                if ($matchingEnv) {
                    $detectedDefaults[] = $matchingEnv->name();
                }
            }
        }

        $selectedCodeEnvironments = collect(multiselect(
            label: sprintf('Which code editors do you use to work on %s?', $this->projectName),
            options: $options->toArray(),
            default: $defaults === [] ? $detectedDefaults : $defaults,
            scroll: $options->count(),
            required: true,
            hint: $detectedDefaults !== [] ? sprintf('Auto-detected %s', Arr::join(array_map(
                fn ($name) => $availableEnvironments->first(fn ($env) => $env->name() === $name)?->displayName() ?? $name,
                $detectedDefaults
            ), ', ', ' & ')) : '',
        ))->sort();

        return $selectedCodeEnvironments->map(
            fn (string $name) => $availableEnvironments->first(fn ($env): bool => $env->name() === $name),
        )->filter()->values();
    }

    /**
     * @return array<int, string>
     */
    protected function buildMcpCommand(McpClient $mcpClient): array
    {
        if ($this->useSail) {
            return ['telescope', './vendor/bin/sail', 'artisan', 'mcp:start', 'telescope'];
        }

        $inWsl = $this->isRunningInWsl();

        return array_filter([
            'telescope',
            $inWsl ? 'wsl' : false,
            $mcpClient->getPhpPath($inWsl),
            $mcpClient->getArtisanPath($inWsl),
            'mcp:start',
            'telescope',
        ]);
    }

    /**
     * @param  array<int, string>  $mcp
     */
    protected function installMcpServers(McpClient $mcpClient, array $mcp): bool
    {
        $path = $mcpClient->mcpConfigPath();
        if (! $path) {
            return $mcpClient->installMcp(
                array_shift($mcp),
                array_shift($mcp),
                $mcp
            );
        }

        $writer = new FileWriter($path);
        $writer->configKey($mcpClient->mcpConfigKey());

        $localKey = array_shift($mcp);
        $command = array_shift($mcp);
        $writer->addServer($localKey, $command, array_values($mcp), []);

        if ($this->configureHttpServer) {
            $httpPath = config('telescope-mcp.http.path', 'telescope-mcp');
            $writer->addHttpServer(
                'telescope-http',
                "https://your-production-url.com/{$httpPath}",
                ['Authorization' => 'Bearer YOUR-TOKEN-HERE']
            );
        }

        return $writer->save();
    }

    protected function shouldConfigureSail(): bool
    {
        return confirm(
            label: 'Laravel Sail detected. Configure Telescope MCP to use Sail?',
            default: $this->config->getSail(),
            hint: 'This will configure the MCP server to run through Sail.',
        );
    }

    protected function shouldConfigureHttpServer(): bool
    {
        return confirm(
            label: 'Configure HTTP access for remote servers (staging/production)?',
            default: false,
            hint: 'Adds HTTP server config to monitor remote environments via Telescope',
        );
    }

    protected function displayHttpConfigurationInstructions(): void
    {
        $token = bin2hex(random_bytes(32));
        $httpPath = config('telescope-mcp.http.path', 'telescope-mcp');

        note(
            <<<NOTE
            {$this->green('✓')} HTTP server configuration added!

            Next steps to enable remote access:

            1. Add to your production/staging .env file:
               TELESCOPE_MCP_AUTH_ENABLED=true
               TELESCOPE_MCP_API_TOKEN={$token}

            2. Update your MCP config file with your production URL:
               Replace "https://your-production-url.com/{$httpPath}"
               with your actual URL

            3. Add the token to your MCP config headers:
               "headers": { "Authorization": "Bearer {$token}" }
            NOTE
        );
    }

    protected function outro(): void
    {
        $this->newLine();
        $this->info(' Installation complete! Restart your IDE to activate the MCP server.');
        $this->newLine();
    }

    protected function isSailInstalled(): bool
    {
        return file_exists(base_path('vendor/bin/sail'))
            && (file_exists(base_path('docker-compose.yml')) || file_exists(base_path('compose.yaml')));
    }

    protected function isRunningInsideSail(): bool
    {
        return get_current_user() === 'sail' || getenv('LARAVEL_SAIL') === '1';
    }

    private function isRunningInWsl(): bool
    {
        return ! empty(getenv('WSL_DISTRO_NAME')) || ! empty(getenv('IS_WSL'));
    }
}
