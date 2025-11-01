<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

final class ExceptionsTool extends TelescopeAbstractTool
{
    protected string $entryType = 'exception';

    public function getShortName(): string
    {
        return 'exceptions';
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getShortName(),
            'description' => 'View and analyze application exceptions from Telescope',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['summary', 'list', 'detail', 'stats', 'search'],
                        'description' => 'Action to perform',
                        'default' => 'list',
                    ],
                    'period' => [
                        'type' => 'string',
                        'enum' => ['5m', '15m', '1h', '6h', '24h', '7d', '14d', '21d', '30d', '3M', '6M', '12M'],
                        'description' => 'Time period for analysis',
                        'default' => '1h',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Number of entries to return (max 25)',
                        'default' => 10,
                    ],
                    'offset' => [
                        'type' => 'integer',
                        'description' => 'Offset for pagination',
                        'default' => 0,
                    ],
                    'id' => [
                        'type' => 'string',
                        'description' => 'Entry ID for detail view',
                    ],
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query (searches class, message, file)',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    /**
     * Get fields to include in list view.
     */
    protected function getListFields(): array
    {
        return [
            'id',
            'content.class',
            'content.message',
            'content.file',
            'content.line',
            'created_at',
        ];
    }

    /**
     * Get searchable fields.
     */
    protected function getSearchableFields(): array
    {
        return [
            'class',
            'message',
            'file',
        ];
    }

    /**
     * Override stats to include exception-specific metrics.
     */
    public function stats(array $arguments = []): array
    {
        $entries = $this->normalizeEntries($this->getEntries($arguments));

        if (empty($entries)) {
            return $this->formatter->formatStats([]);
        }

        // Count by exception class
        $classCounts = [];
        $fileCounts = [];

        foreach ($entries as $entry) {
            $class = $entry['content']['class'] ?? 'Unknown';
            $file = $entry['content']['file'] ?? 'Unknown';

            $classCounts[$class] = ($classCounts[$class] ?? 0) + 1;
            $fileCounts[$file] = ($fileCounts[$file] ?? 0) + 1;
        }

        // Sort and limit
        arsort($classCounts);
        arsort($fileCounts);

        return $this->formatter->formatStats([
            'total_exceptions' => count($entries),
            'by_class' => array_slice($classCounts, 0, 10, true),
            'by_file' => array_slice($fileCounts, 0, 10, true),
            'unique_classes' => count($classCounts),
            'unique_files' => count($fileCounts),
            'most_common_exception' => array_key_first($classCounts),
            'most_common_file' => array_key_first($fileCounts),
        ]);
    }
}
