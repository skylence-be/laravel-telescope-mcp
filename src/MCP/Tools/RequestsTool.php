<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

use Skylence\TelescopeMcp\Services\PaginationManager;
use Skylence\TelescopeMcp\Services\ResponseFormatter;
use Skylence\TelescopeMcp\Services\RouteFilter;

final class RequestsTool extends TelescopeAbstractTool
{
    protected string $entryType = 'request';
    protected RouteFilter $routeFilter;

    public function __construct(
        array $config,
        PaginationManager $pagination,
        ResponseFormatter $formatter,
        RouteFilter $routeFilter
    ) {
        parent::__construct($config, $pagination, $formatter);
        $this->routeFilter = $routeFilter;
    }

    public function getShortName(): string
    {
        return 'requests';
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getShortName(),
            'description' => 'Analyze HTTP requests handled by your application. Can filter by route type (web/api).',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['summary', 'list', 'detail', 'stats', 'search', 'slow'],
                        'description' => 'Action to perform',
                        'default' => 'list',
                    ],
                    'period' => [
                        'type' => 'string',
                        'enum' => ['5m', '15m', '1h', '6h', '24h', '7d', '14d', '21d', '30d', '3M', '6M', '12M'],
                        'description' => 'Time period for analysis',
                        'default' => '1h',
                    ],
                    'route_type' => [
                        'type' => 'string',
                        'description' => 'Filter by route type (all, api, web, other). Defaults to "all".',
                        'default' => 'all',
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
                    'status' => [
                        'type' => 'integer',
                        'description' => 'Filter by HTTP status code',
                    ],
                    'method' => [
                        'type' => 'string',
                        'description' => 'Filter by HTTP method',
                    ],
                    'min_duration' => [
                        'type' => 'integer',
                        'description' => 'Minimum duration in ms',
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
            'slow' => $this->getSlowRequests($arguments),
            default => parent::execute($arguments),
        };
    }

    /**
     * Override to apply route filtering.
     */
    protected function getEntries(array $arguments = []): array
    {
        $entries = parent::getEntries($arguments);

        // Apply route type filtering if specified
        $routeType = $arguments['route_type'] ?? 'all';
        if ($routeType !== 'all') {
            $normalizedEntries = $this->normalizeEntries($entries);
            $filteredEntries = $this->routeFilter->filterRequests($normalizedEntries, $routeType);

            // Convert back to entry objects (extract from normalized structure)
            // We need to map back to original entries based on ID
            $filteredIds = array_column($filteredEntries, 'id');
            $entries = array_filter($entries, function ($entry) use ($filteredIds) {
                return in_array($entry->id ?? null, $filteredIds, true);
            });
            $entries = array_values($entries);
        }

        return $entries;
    }

    /**
     * Get slow requests.
     */
    protected function getSlowRequests(array $arguments): array
    {
        $threshold = $arguments['min_duration'] ?? $this->config['slow_request_ms'] ?? 1000;
        $limit = $this->pagination->getLimit($arguments['limit'] ?? 10);

        $entries = $this->normalizeEntries($this->getEntries($arguments));

        $slowRequests = array_filter($entries, function ($entry) use ($threshold) {
            return ($entry['content']['duration'] ?? 0) >= $threshold;
        });

        usort($slowRequests, function ($a, $b) {
            return $b['content']['duration'] <=> $a['content']['duration'];
        });

        $slowRequests = array_slice($slowRequests, 0, $limit);

        return $this->formatter->format([
            'slow_requests' => array_map(function ($request) {
                return [
                    'id' => $request['id'],
                    'method' => $request['content']['method'] ?? '',
                    'uri' => $request['content']['uri'] ?? '',
                    'status' => $request['content']['response_status'] ?? 0,
                    'duration' => $request['content']['duration'] ?? 0,
                    'controller' => $request['content']['controller_action'] ?? '',
                    'memory' => $request['content']['memory'] ?? 0,
                    'created_at' => $request['created_at'] ?? '',
                ];
            }, $slowRequests),
            'threshold_ms' => $threshold,
            'total_slow' => count($slowRequests),
        ], 'standard');
    }

    /**
     * Get fields to include in list view.
     */
    protected function getListFields(): array
    {
        return [
            'id',
            'content.method',
            'content.uri',
            'content.controller_action',
            'content.response_status',
            'content.duration',
            'content.memory',
            'created_at',
        ];
    }

    /**
     * Get searchable fields.
     */
    protected function getSearchableFields(): array
    {
        return [
            'uri',
            'controller_action',
            'method',
            'ip_address',
        ];
    }

    /**
     * Override stats to include request-specific metrics.
     */
    public function stats(array $arguments = []): array
    {
        try {
            $entries = $this->normalizeEntries($this->getEntries($arguments));
            $routeType = $arguments['route_type'] ?? 'all';

            if (empty($entries)) {
                return $this->formatter->formatStats([
                    'total_requests' => 0,
                    'route_type' => $routeType,
                    'message' => 'No requests found for the specified period',
                ]);
            }

            $durations = array_filter(
                array_map(fn ($e) => $e['content']['duration'] ?? 0, $entries),
                fn ($d) => is_numeric($d)
            );
            $memories = array_filter(
                array_map(fn ($e) => $e['content']['memory'] ?? 0, $entries),
                fn ($m) => is_numeric($m)
            );
            $statuses = array_map(fn ($e) => $e['content']['response_status'] ?? 0, $entries);

            // Ensure we have valid data
            if (empty($durations)) {
                $durations = [0];
            }
            if (empty($memories)) {
                $memories = [0];
            }

            $statusCounts = array_count_values($statuses);
            $successCount = 0;
            $errorCount = 0;

            foreach ($statusCounts as $status => $count) {
                if ($status >= 200 && $status < 400) {
                    $successCount += $count;
                } elseif ($status >= 400) {
                    $errorCount += $count;
                }
            }

            $totalRequests = count($entries);

            return $this->formatter->formatStats([
                'total_requests' => $totalRequests,
                'route_type' => $routeType,
                'duration' => [
                    'avg' => count($durations) > 0 ? array_sum($durations) / count($durations) : 0,
                    'min' => count($durations) > 0 ? min($durations) : 0,
                    'max' => count($durations) > 0 ? max($durations) : 0,
                    'p50' => $this->percentile($durations, 50),
                    'p95' => $this->percentile($durations, 95),
                    'p99' => $this->percentile($durations, 99),
                ],
                'memory' => [
                    'avg' => count($memories) > 0 ? array_sum($memories) / count($memories) : 0,
                    'min' => count($memories) > 0 ? min($memories) : 0,
                    'max' => count($memories) > 0 ? max($memories) : 0,
                ],
                'status' => [
                    'success' => $successCount,
                    'error' => $errorCount,
                    'error_rate' => $totalRequests > 0 ? round(($errorCount / $totalRequests) * 100, 2).'%' : '0%',
                    'breakdown' => $statusCounts,
                ],
                'methods' => $this->getMethodBreakdown($entries),
                'endpoints' => $this->getTopEndpoints($entries, 5),
            ]);
        } catch (\Exception $e) {
            return $this->formatError("Failed to calculate request statistics: {$e->getMessage()}");
        }
    }

    /**
     * Get method breakdown.
     */
    protected function getMethodBreakdown(array $entries): array
    {
        $methods = [];

        foreach ($entries as $entry) {
            $method = $entry['content']['method'] ?? 'UNKNOWN';
            $methods[$method] = ($methods[$method] ?? 0) + 1;
        }

        return $methods;
    }

    /**
     * Get top endpoints by request count.
     */
    protected function getTopEndpoints(array $entries, int $limit = 5): array
    {
        $endpoints = [];

        foreach ($entries as $entry) {
            $endpoint = $entry['content']['controller_action'] ?? $entry['content']['uri'] ?? 'unknown';

            if (! isset($endpoints[$endpoint])) {
                $endpoints[$endpoint] = [
                    'count' => 0,
                    'avg_duration' => 0,
                    'durations' => [],
                ];
            }

            $endpoints[$endpoint]['count']++;
            $endpoints[$endpoint]['durations'][] = $entry['content']['duration'] ?? 0;
        }

        foreach ($endpoints as $endpoint => &$data) {
            $data['avg_duration'] = array_sum($data['durations']) / count($data['durations']);
            unset($data['durations']);
        }

        uasort($endpoints, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($endpoints, 0, $limit, true);
    }
}
