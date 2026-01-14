<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\Services;

class RouteFilter
{
    private array $config;
    private array $routeGroups;
    private array $exclusions;
    private string $matchingStrategy;

    public function __construct()
    {
        $this->config = config('telescope-mcp.overview', []);
        $this->routeGroups = $this->config['route_groups'] ?? [];
        $this->exclusions = $this->config['exclude'] ?? [];
        $this->matchingStrategy = $this->config['matching_strategy'] ?? 'any';
    }

    /**
     * Categorize a request into a route group.
     *
     * @param array $requestContent The request entry content
     * @return string|null The route group name, or null if excluded
     */
    public function categorizeRequest(array $requestContent): ?string
    {
        // First check if request should be excluded
        if ($this->shouldExclude($requestContent)) {
            return null;
        }

        // Try to match against defined route groups (first match wins)
        foreach ($this->routeGroups as $groupName => $groupConfig) {
            if ($this->matchesGroup($requestContent, $groupConfig)) {
                return $groupName;
            }
        }

        // If no group matched, categorize as 'other'
        return 'other';
    }

    /**
     * Check if a request should be excluded from analysis.
     *
     * @param array $requestContent The request entry content
     * @return bool
     */
    public function shouldExclude(array $requestContent): bool
    {
        $uri = $requestContent['uri'] ?? '';
        $middleware = $requestContent['middleware'] ?? [];
        $controllerAction = $requestContent['controller_action'] ?? '';

        // Check URI exclusions
        foreach ($this->exclusions['uris'] ?? [] as $pattern) {
            if ($this->matchesPattern($uri, $pattern)) {
                return true;
            }
        }

        // Check middleware exclusions
        foreach ($this->exclusions['middleware'] ?? [] as $excludedMiddleware) {
            if (in_array($excludedMiddleware, $middleware, true)) {
                return true;
            }
        }

        // Check controller action exclusions
        foreach ($this->exclusions['controller_actions'] ?? [] as $excludedAction) {
            if ($this->matchesPattern($controllerAction, $excludedAction)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter requests by route type.
     *
     * @param array $requests Array of request entries
     * @param string|null $routeType The route type to filter by (null = all)
     * @return array Filtered requests
     */
    public function filterRequests(array $requests, ?string $routeType = null): array
    {
        // If no filter specified, return all non-excluded requests
        if ($routeType === null || $routeType === 'all') {
            return array_filter($requests, function ($request) {
                return $this->categorizeRequest($request['content']) !== null;
            });
        }

        // Filter by specific route type
        return array_filter($requests, function ($request) use ($routeType) {
            return $this->categorizeRequest($request['content']) === $routeType;
        });
    }

    /**
     * Get a breakdown of requests by route group.
     *
     * @param array $requests Array of request entries
     * @return array Breakdown stats per route group
     */
    public function getRouteBreakdown(array $requests): array
    {
        $breakdown = [];

        // Initialize breakdown for each configured group
        foreach (array_keys($this->routeGroups) as $groupName) {
            $breakdown[$groupName] = [
                'request_count' => 0,
                'total_duration' => 0,
                'error_count' => 0,
                'durations' => [],
            ];
        }

        // Add 'other' category
        $breakdown['other'] = [
            'request_count' => 0,
            'total_duration' => 0,
            'error_count' => 0,
            'durations' => [],
        ];

        // Categorize and aggregate each request
        foreach ($requests as $request) {
            $content = $request['content'];
            $group = $this->categorizeRequest($content);

            // Skip excluded requests
            if ($group === null) {
                continue;
            }

            $breakdown[$group]['request_count']++;
            $breakdown[$group]['total_duration'] += $content['duration'] ?? 0;
            $breakdown[$group]['durations'][] = $content['duration'] ?? 0;

            // Count errors (4xx and 5xx)
            $status = $content['response_status'] ?? 200;
            if ($status >= 400) {
                $breakdown[$group]['error_count']++;
            }
        }

        // Calculate final metrics for each group
        foreach ($breakdown as $group => &$stats) {
            if ($stats['request_count'] > 0) {
                $stats['avg_response_time_ms'] = round($stats['total_duration'] / $stats['request_count'], 2);
                $stats['error_rate'] = round($stats['error_count'] / $stats['request_count'], 4);
                $stats['p95_response_time_ms'] = $this->calculatePercentile($stats['durations'], 95);
            } else {
                $stats['avg_response_time_ms'] = 0;
                $stats['error_rate'] = 0;
                $stats['p95_response_time_ms'] = 0;
            }

            // Remove temporary fields
            unset($stats['total_duration'], $stats['durations'], $stats['error_count']);
        }

        // Remove groups with zero requests
        return array_filter($breakdown, fn($stats) => $stats['request_count'] > 0);
    }

    /**
     * Check if a request matches a route group configuration.
     *
     * @param array $requestContent The request entry content
     * @param array $groupConfig The group configuration
     * @return bool
     */
    private function matchesGroup(array $requestContent, array $groupConfig): bool
    {
        $middleware = $requestContent['middleware'] ?? [];
        $uri = $requestContent['uri'] ?? '';

        // Check middleware match
        $middlewareMatch = false;
        $groupMiddleware = $groupConfig['middleware'] ?? [];

        if (!empty($groupMiddleware)) {
            $matchedCount = 0;
            foreach ($groupMiddleware as $pattern) {
                foreach ($middleware as $m) {
                    if ($this->matchesPattern($m, $pattern)) {
                        $matchedCount++;
                        break;
                    }
                }
            }

            if ($this->matchingStrategy === 'all') {
                // ALL middleware patterns must match
                $middlewareMatch = $matchedCount === count($groupMiddleware);
            } else {
                // ANY middleware pattern must match (default)
                $middlewareMatch = $matchedCount > 0;
            }
        } else {
            // No middleware specified means middleware check passes
            $middlewareMatch = true;
        }

        // Check URI prefix match (if specified)
        $uriMatch = true;
        if (isset($groupConfig['uri_prefix']) && $groupConfig['uri_prefix'] !== null) {
            $uriMatch = str_starts_with($uri, $groupConfig['uri_prefix']);
        }

        // Both conditions must be true
        return $middlewareMatch && $uriMatch;
    }

    /**
     * Check if a string matches a pattern (supports wildcards).
     *
     * @param string $string The string to check
     * @param string $pattern The pattern (supports * wildcard)
     * @return bool
     */
    private function matchesPattern(string $string, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = '/^' . str_replace(['/', '*'], ['\/', '.*'], $pattern) . '$/';

        return preg_match($regex, $string) === 1;
    }

    /**
     * Calculate percentile from an array of values.
     *
     * @param array $values Array of numeric values
     * @param float $percentile Percentile (0-100)
     * @return float
     */
    private function calculatePercentile(array $values, float $percentile): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $index = ceil(count($values) * ($percentile / 100)) - 1;
        $index = max(0, min($index, count($values) - 1));

        return round($values[$index], 2);
    }

    /**
     * Get context-aware thresholds for a specific route type.
     *
     * @param string $routeType The route type
     * @return array Thresholds for this route type
     */
    public function getThresholds(string $routeType): array
    {
        $thresholds = $this->config['thresholds'][$routeType] ?? [];

        // Fall back to global config if not specified
        return [
            'slow_request_ms' => $thresholds['slow_request_ms'] ?? config('telescope-mcp.slow_request_ms', 1000),
            'acceptable_error_rate' => $thresholds['acceptable_error_rate'] ?? 0.05,
        ];
    }
}
