<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\Install\Contracts;

use Skylence\TelescopeMcp\Install\Enums\Platform;

interface DetectionStrategy
{
    /**
     * @param  array{command?:string, basePath?:string, files?:array<string>, paths?:array<string>}  $config
     */
    public function detect(array $config, ?Platform $platform = null): bool;
}
