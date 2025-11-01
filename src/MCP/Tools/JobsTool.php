<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

final class JobsTool extends TelescopeAbstractTool
{
    protected string $entryType = 'job';

    public function getShortName(): string
    {
        return 'jobs';
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getShortName(),
            'description' => 'View queued jobs executed via Telescope',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['summary', 'list', 'detail', 'stats', 'search', 'failed'],
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
                        'description' => 'Search query',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    public function execute(array $arguments = []): array
    {
        $action = $arguments['action'] ?? 'list';

        return match ($action) {
            'failed' => $this->getFailedJobs($arguments),
            default => parent::execute($arguments),
        };
    }

    protected function getFailedJobs(array $arguments): array
    {
        $entries = $this->normalizeEntries($this->getEntries($arguments));

        $failedJobs = array_filter($entries, function ($entry) {
            return ($entry['content']['status'] ?? '') === 'failed';
        });

        $limit = $this->pagination->getLimit($arguments['limit'] ?? 10);
        $failedJobs = array_slice($failedJobs, 0, $limit);

        return $this->formatter->format([
            'failed_jobs' => $failedJobs,
            'total_failed' => count($failedJobs),
        ], 'standard');
    }

    protected function getListFields(): array
    {
        return [
            'id',
            'content.name',
            'content.status',
            'content.queue',
            'created_at',
        ];
    }

    protected function getSearchableFields(): array
    {
        return [
            'name',
            'queue',
        ];
    }

    public function stats(array $arguments = []): array
    {
        $entries = $this->normalizeEntries($this->getEntries($arguments));

        if (empty($entries)) {
            return $this->formatter->formatStats([]);
        }

        $statusCounts = [];
        $queueCounts = [];
        $jobCounts = [];

        foreach ($entries as $entry) {
            $status = $entry['content']['status'] ?? 'unknown';
            $queue = $entry['content']['queue'] ?? 'default';
            $name = $entry['content']['name'] ?? 'unknown';

            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            $queueCounts[$queue] = ($queueCounts[$queue] ?? 0) + 1;
            $jobCounts[$name] = ($jobCounts[$name] ?? 0) + 1;
        }

        arsort($jobCounts);

        return $this->formatter->formatStats([
            'total_jobs' => count($entries),
            'by_status' => $statusCounts,
            'by_queue' => $queueCounts,
            'by_job' => array_slice($jobCounts, 0, 10, true),
        ]);
    }
}
