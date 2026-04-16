<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\Install;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Skylence\TelescopeMcp\Install\CodeEnvironment\ClaudeCode;
use Skylence\TelescopeMcp\Install\CodeEnvironment\CodeEnvironment;
use Skylence\TelescopeMcp\Install\CodeEnvironment\Cursor;
use Skylence\TelescopeMcp\Install\CodeEnvironment\PhpStorm;
use Skylence\TelescopeMcp\Install\CodeEnvironment\VSCode;
use Skylence\TelescopeMcp\Install\Detection\DetectionStrategyFactory;
use Skylence\TelescopeMcp\Install\Enums\Platform;

class CodeEnvironmentsDetector
{
    public function __construct(
        private readonly Container $container,
        private readonly DetectionStrategyFactory $strategyFactory
    ) {}

    /**
     * @return array<int, class-string<CodeEnvironment>>
     */
    protected function getCodeEnvironmentClasses(): array
    {
        return [
            ClaudeCode::class,
            Cursor::class,
            VSCode::class,
            PhpStorm::class,
        ];
    }

    /**
     * @return array<string>
     */
    public function discoverSystemInstalledCodeEnvironments(): array
    {
        $platform = Platform::current();

        return $this->getCodeEnvironments()
            ->filter(fn (CodeEnvironment $program): bool => $program->detectOnSystem($platform))
            ->map(fn (CodeEnvironment $program): string => $program->name())
            ->values()
            ->toArray();
    }

    /**
     * @return array<string>
     */
    public function discoverProjectInstalledCodeEnvironments(string $basePath): array
    {
        return $this->getCodeEnvironments()
            ->filter(fn (CodeEnvironment $program): bool => $program->detectInProject($basePath))
            ->map(fn (CodeEnvironment $program): string => $program->name())
            ->values()
            ->toArray();
    }

    /**
     * @return Collection<string, CodeEnvironment>
     */
    public function getCodeEnvironments(): Collection
    {
        return collect($this->getCodeEnvironmentClasses())
            ->map(fn (string $className) => new $className($this->strategyFactory));
    }
}
