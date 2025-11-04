# Laravel Telescope MCP - Guidelines

This project uses Laravel Telescope MCP for real-time monitoring, performance analysis, and debugging of the Laravel application through Telescope data.

---

<laravel-telescope-mcp-guidelines>
# Laravel Telescope MCP Guidelines

Laravel Telescope MCP provides powerful Model Context Protocol (MCP) tools for analyzing Laravel Telescope data to monitor performance, debug issues, and optimize your Laravel application in real-time.

## Package Information
This application uses `skylence/laravel-telescope-mcp` which provides:
- Real-time request and query monitoring
- Performance analysis and bottleneck detection
- N+1 query detection and optimization suggestions
- Exception tracking and error analysis
- Job queue monitoring
- Cache operation analysis
- System health scoring with context-aware thresholds
- Route-based filtering (API vs Web routes)

## Available MCP Tools

### Overview Tool
**Tool:** `telescope_overview`
**Purpose:** Get a comprehensive system health overview with performance metrics and critical issues

**Usage:**
```json
{
  "tool": "telescope_overview",
  "params": {
    "period": "1h",
    "route_type": "all",
    "include_recommendations": true,
    "include_breakdown": false
  }
}
```

**Parameters:**
- `period`: Time window - `5m`, `15m`, `1h`, `6h`, `24h`, `7d`, `14d`, `21d`, `30d`, `3M`, `6M`, `12M` (default: `1h`)
- `route_type`: Filter by route type - `all`, `api`, `web`, `other` (default: `all`)
- `include_recommendations`: Include optimization recommendations (default: `true`)
- `include_breakdown`: Include route group breakdown (default: `false`, always included when `route_type=all`)

**Output Includes:**
- **Health Status**: Score (0-100), status label (healthy/good/warning/critical), issues list
- **Performance Metrics**: Request times (avg, p95), database stats, memory usage
- **Critical Issues**: Top 5 issues with severity and recommended actions
- **System Stats**: Request counts, query counts, exceptions, jobs, cache hit rate
- **Recent Errors**: Last 3 exceptions with details
- **Route Breakdown**: Performance breakdown by route group (when applicable)
- **Recommendations**: Actionable optimization suggestions (when enabled)

**When to Use:**
- Quick health check of the application
- Monitoring production performance
- Identifying performance bottlenecks
- Comparing API vs web route performance
- Getting optimization recommendations

### Requests Tool
**Tool:** `requests`
**Purpose:** Analyze HTTP requests with detailed filtering and statistics

**Usage:**
```json
{
  "tool": "requests",
  "params": {
    "action": "stats",
    "period": "1h",
    "route_type": "api",
    "limit": 10
  }
}
```

**Parameters:**
- `action`: Action to perform - `summary`, `list`, `detail`, `stats`, `search`, `slow` (default: `list`)
- `period`: Time window (same options as overview tool)
- `route_type`: Filter by route type - `all`, `api`, `web`, `other` (default: `all`)
- `limit`: Number of entries to return (max 25, default: 10)
- `offset`: Pagination offset (default: 0)
- `id`: Entry ID for detail view
- `query`: Search query
- `status`: Filter by HTTP status code
- `method`: Filter by HTTP method (GET, POST, etc.)
- `min_duration`: Minimum duration in milliseconds

**Actions:**
- **`list`**: Paginated list of requests with key details
- **`detail`**: Full details of a specific request by ID
- **`stats`**: Statistical analysis (duration percentiles, memory, status codes, methods, top endpoints)
- **`search`**: Search requests by URI, controller, method, IP address
- **`slow`**: Find slow requests exceeding a threshold
- **`summary`**: High-level summary of request data

**When to Use:**
- Finding slow endpoints
- Analyzing API performance separately from web routes
- Tracking down specific request issues
- Understanding request patterns and methods
- Debugging specific request IDs

### Queries Tool
**Tool:** `queries`
**Purpose:** Analyze database queries, detect N+1 problems, find slow queries

**Usage:**
```json
{
  "tool": "queries",
  "params": {
    "action": "stats",
    "period": "1h"
  }
}
```

**Parameters:**
- Same as requests tool (without `route_type`)
- `action`: `summary`, `list`, `detail`, `stats`, `search`, `slow`

**Stats Output Includes:**
- Total queries count
- Average, min, max query time
- Percentiles (p50, p95, p99)
- Slow queries count
- Query type breakdown (SELECT, INSERT, UPDATE, DELETE)

**When to Use:**
- Detecting N+1 query problems
- Finding slow database queries
- Optimizing query performance
- Understanding database usage patterns

### Exceptions Tool
**Tool:** `exceptions`
**Purpose:** Track and analyze application exceptions and errors

**Usage:**
```json
{
  "tool": "exceptions",
  "params": {
    "action": "list",
    "period": "24h",
    "limit": 20
  }
}
```

**Parameters:**
- Same as requests tool (without `route_type`)

**When to Use:**
- Monitoring application errors
- Debugging recurring exceptions
- Tracking exception patterns over time
- Understanding error frequency

### Jobs Tool
**Tool:** `jobs`
**Purpose:** Monitor queued jobs execution and failures

**Usage:**
```json
{
  "tool": "jobs",
  "params": {
    "action": "stats",
    "period": "1h"
  }
}
```

**Parameters:**
- Same as requests tool (without `route_type`)

**When to Use:**
- Monitoring queue health
- Tracking failed jobs
- Analyzing job performance
- Debugging queue issues

### Cache Tool
**Tool:** `cache`
**Purpose:** Analyze cache operations and hit rates

**Usage:**
```json
{
  "tool": "cache",
  "params": {
    "action": "stats",
    "period": "1h"
  }
}
```

**Parameters:**
- Same as requests tool (without `route_type`)

**When to Use:**
- Monitoring cache effectiveness
- Analyzing cache hit/miss rates
- Optimizing caching strategy

## Route Type Filtering

### Overview
Routes are automatically categorized based on middleware and URI patterns. This allows you to analyze API and web routes separately, which is critical because:
- API routes are typically called more frequently
- API routes should be faster (stricter thresholds)
- Web routes can be slower (rendering HTML)
- Mixing them in analysis can mask performance issues

### Configuration
Route filtering is configured in `config/telescope-mcp.php`:

```php
'overview' => [
    'route_groups' => [
        'api' => [
            'middleware' => ['api'],
            'uri_prefix' => null,
        ],
        'web' => [
            'middleware' => ['web'],
            'uri_prefix' => null,
        ],
    ],
    'exclude' => [
        'uris' => [
            'telescope-mcp/*',
            'telescope/*',
            'horizon/*',
            'pulse/*',
            '_debugbar/*',
            'livewire/*',
        ],
    ],
    'thresholds' => [
        'api' => [
            'slow_request_ms' => 500,
            'acceptable_error_rate' => 0.01,
        ],
        'web' => [
            'slow_request_ms' => 1500,
            'acceptable_error_rate' => 0.05,
        ],
    ],
],
```

### How Routes Are Categorized
1. **Middleware Matching** (Primary): Routes with `api` middleware → `api` group, routes with `web` middleware → `web` group
2. **URI Prefix** (Secondary): Optional additional filtering by URI pattern
3. **Exclusions** (First): Routes matching exclusion patterns are filtered out entirely
4. **Fallback**: Routes not matching any group → `other` category

### Context-Aware Thresholds
Different route types have different performance expectations:
- **API routes**: Should be fast (500ms threshold), low error rate (1%)
- **Web routes**: Can be slower (1500ms threshold), higher error rate acceptable (5%)

Health scoring adjusts based on the route type being analyzed.

## Best Practices

### Performance Monitoring

**Regular Health Checks:**
```json
{"tool": "telescope_overview", "params": {"period": "1h", "route_type": "all"}}
```

**API-Specific Monitoring:**
```json
{"tool": "telescope_overview", "params": {"period": "1h", "route_type": "api", "include_recommendations": true}}
```

**Find Slow Endpoints:**
```json
{"tool": "requests", "params": {"action": "slow", "route_type": "api", "min_duration": 500}}
```

### Database Optimization

**Detect N+1 Queries:**
```json
{"tool": "telescope_overview", "params": {"period": "1h", "include_recommendations": true}}
```

**Find Slow Queries:**
```json
{"tool": "queries", "params": {"action": "slow", "period": "1h", "min_duration": 100}}
```

### Error Tracking

**Monitor Recent Errors:**
```json
{"tool": "exceptions", "params": {"action": "list", "period": "24h"}}
```

**Get Exception Statistics:**
```json
{"tool": "exceptions", "params": {"action": "stats", "period": "7d"}}
```

### Queue Monitoring

**Check Queue Health:**
```json
{"tool": "jobs", "params": {"action": "stats", "period": "1h"}}
```

**Find Failed Jobs:**
```json
{"tool": "jobs", "params": {"action": "search", "query": "failed"}}
```

## Configuration

### Environment Variables
```env
# Enable MCP server
TELESCOPE_MCP_ENABLED=true

# Configure path
TELESCOPE_MCP_PATH=telescope-mcp

# Authentication
TELESCOPE_MCP_AUTH_ENABLED=true
TELESCOPE_MCP_API_TOKEN=your-secure-token-here

# Performance thresholds
TELESCOPE_MCP_SLOW_REQUEST_MS=1000
TELESCOPE_MCP_SLOW_QUERY_MS=100
TELESCOPE_MCP_HIGH_MEMORY_MB=50
TELESCOPE_MCP_N_PLUS_ONE_THRESHOLD=3

# Logging
TELESCOPE_MCP_LOGGING_ENABLED=true
```

### Route Group Customization
You can add custom route groups in `config/telescope-mcp.php`:

```php
'route_groups' => [
    'api' => [
        'middleware' => ['api'],
    ],
    'web' => [
        'middleware' => ['web'],
    ],
    'admin' => [
        'middleware' => ['auth', 'admin'],
        'uri_prefix' => 'admin/',
    ],
],
```

### Excluding Routes
Exclude internal tools, debugging routes, or third-party packages:

```php
'exclude' => [
    'uris' => [
        'telescope-mcp/*',
        'telescope/*',
        'horizon/*',
        'pulse/*',
        '_debugbar/*',
        'livewire/*',
        'admin/telescope/*',
    ],
    'middleware' => [
        'telescope',
    ],
    'controller_actions' => [
        'Closure',  // Optionally exclude closure routes
    ],
],
```

## Common Use Cases

### 1. Production Health Monitoring
```json
// Get overall health with route breakdown
{
  "tool": "telescope_overview",
  "params": {
    "period": "1h",
    "route_type": "all",
    "include_recommendations": true
  }
}

// Focus on API performance
{
  "tool": "telescope_overview",
  "params": {
    "period": "1h",
    "route_type": "api"
  }
}
```

### 2. Performance Optimization
```json
// Find bottlenecks
{
  "tool": "telescope_overview",
  "params": {
    "period": "6h",
    "include_recommendations": true
  }
}

// Analyze slow API requests
{
  "tool": "requests",
  "params": {
    "action": "slow",
    "route_type": "api",
    "period": "24h",
    "min_duration": 500
  }
}

// Find slow queries
{
  "tool": "queries",
  "params": {
    "action": "slow",
    "period": "1h",
    "min_duration": 100
  }
}
```

### 3. Debugging Issues
```json
// Find specific request
{
  "tool": "requests",
  "params": {
    "action": "search",
    "query": "users/profile",
    "period": "1h"
  }
}

// Get full request details
{
  "tool": "requests",
  "params": {
    "action": "detail",
    "id": "request-uuid-here"
  }
}

// Track recent errors
{
  "tool": "exceptions",
  "params": {
    "action": "list",
    "period": "24h",
    "limit": 20
  }
}
```

### 4. Comparing API vs Web Performance
```json
// Get API stats
{
  "tool": "requests",
  "params": {
    "action": "stats",
    "route_type": "api",
    "period": "1h"
  }
}

// Get web stats
{
  "tool": "requests",
  "params": {
    "action": "stats",
    "route_type": "web",
    "period": "1h"
  }
}

// Or get both with breakdown
{
  "tool": "telescope_overview",
  "params": {
    "period": "1h",
    "route_type": "all"
  }
}
```

## Understanding Health Scores

### Score Calculation
Health scores start at 100 and deduct points based on issues:
- High average response time (>threshold): -20 points
- Error rate >5x acceptable: -30 points
- Error rate >acceptable but <5x: -10 points
- >10 slow queries: -15 points
- >10 exceptions: -15 points

### Status Labels
- **90-100**: `healthy` - Everything is running optimally
- **70-89**: `good` - Minor issues, but generally okay
- **50-69**: `warning` - Significant issues need attention
- **0-49**: `critical` - Serious problems require immediate action

### Context-Aware Scoring
Thresholds adjust based on route type:
- **API routes** judged by stricter standards (500ms, 1% error rate)
- **Web routes** allowed more lenience (1500ms, 5% error rate)

## Common Issues and Solutions

### "No requests found for the specified period"
**Problem:** No Telescope data available for the time period
**Solution:**
- Ensure Telescope is installed and enabled
- Check if the application has received traffic during the period
- Verify Telescope is recording request data
- Try a longer time period

### "Route breakdown shows mostly 'other' category"
**Problem:** Routes not matching configured groups
**Solution:**
- Check that routes have the expected middleware (`api` or `web`)
- Review route group configuration in `config/telescope-mcp.php`
- Verify middleware names match your application's middleware

### "High error rate in API routes"
**Problem:** API error rate exceeds acceptable threshold
**Solution:**
- Use `requests` tool with `status` filter to find 4xx/5xx responses
- Use `exceptions` tool to track down error causes
- Check recent errors in overview output
- Review critical issues for specific endpoint problems

### "Slow query warnings"
**Problem:** Multiple slow queries detected
**Solution:**
- Use `queries` tool to find specific slow queries
- Check for N+1 query patterns in overview critical issues
- Add database indexes where recommended
- Use eager loading for relationships

### "Health score is worse for 'all' routes than 'api' routes"
**Problem:** Web routes dragging down overall score
**Solution:** This is normal! Web routes are slower (HTML rendering, assets)
- Analyze API and web routes separately
- Use route-specific thresholds
- Focus optimization on the route type that matters most

## Integration Examples

### CI/CD Health Checks
Monitor application health in CI/CD pipelines:
```bash
# Get health status
curl -X POST https://your-app.com/telescope-mcp \
  -H "Authorization: Bearer ${TELESCOPE_MCP_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"tool": "telescope_overview", "params": {"period": "5m", "route_type": "all"}}'
```

### Monitoring Dashboards
Regularly query overview tool for dashboard metrics:
- Overall health score
- Route breakdown by type
- Critical issues count
- Top slow endpoints

### Alert Systems
Set up alerts based on:
- Health score drops below threshold (e.g., <70)
- Error rate exceeds acceptable limits
- Slow query count spikes
- Exception count increases

## Tips for Effective Monitoring

1. **Use appropriate time periods**:
   - Real-time monitoring: `5m`, `15m`, `1h`
   - Trend analysis: `24h`, `7d`, `30d`
   - Historical review: `3M`, `6M`, `12M`

2. **Filter by route type**:
   - Always analyze API and web routes separately for accurate insights
   - Use `route_type=all` with breakdown for comparison

3. **Enable recommendations**:
   - Include recommendations when investigating performance issues
   - Recommendations are actionable and specific to your data

4. **Monitor regularly**:
   - Check overview tool hourly in production
   - Set up automated monitoring with alerts
   - Review slow requests and queries daily

5. **Act on critical issues**:
   - Critical issues are prioritized by severity
   - Address high-severity bottlenecks first
   - Fix N+1 queries immediately

## Documentation

For more information, visit:
- GitHub: https://github.com/skylence-be/laravel-telescope-mcp
- Laravel Telescope: https://laravel.com/docs/telescope
- MCP Protocol: https://modelcontextprotocol.io/

</laravel-telescope-mcp-guidelines>
