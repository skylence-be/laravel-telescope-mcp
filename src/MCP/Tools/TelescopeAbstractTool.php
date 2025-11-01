<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

use Illuminate\Support\Facades\App;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\Storage\EntryQueryOptions;
use Skylence\TelescopeMcp\Services\PaginationManager;
use Skylence\TelescopeMcp\Services\ResponseFormatter;

abstract class TelescopeAbstractTool extends AbstractTool
{
    protected array $config;
    protected PaginationManager $pagination;
    protected ResponseFormatter $formatter;
    protected EntriesRepository $storage;

    /**
     * The Telescope entry type this tool handles.
     */
    protected string $entryType = '';

    public function __construct(
        array $config,
        PaginationManager $pagination,
        ResponseFormatter $formatter
    ) {
        $this->config = $config;
        $this->pagination = $pagination;
        $this->formatter = $formatter;

        try {
            $this->storage = App::make(EntriesRepository::class);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                'Laravel Telescope is not installed or configured. Please install it first: composer require laravel/telescope',
                0,
                $e
            );
        }
    }

    /**
     * Execute the tool with given arguments.
     */
    public function execute(array $arguments = []): array
    {
        $action = $arguments['action'] ?? 'list';

        return match ($action) {
            'summary' => $this->summary($arguments),
            'list' => $this->list($arguments),
            'detail' => $this->detail($arguments['id'] ?? '', $arguments),
            'stats' => $this->stats($arguments),
            'search' => $this->search($arguments),
            default => $this->list($arguments),
        };
    }

    /**
     * Get summary view of the data.
     */
    public function summary(array $arguments = []): array
    {
        $entries = $this->normalizeEntries($this->getEntries($arguments));

        return $this->formatter->formatSummary([
            'total' => count($entries),
            'type' => $this->entryType,
            'period' => $arguments['period'] ?? '1h',
            'stats' => $this->calculateStats($entries),
        ]);
    }

    /**
     * Get list view of the data.
     */
    public function list(array $arguments = []): array
    {
        $limit = $this->pagination->getLimit($arguments['limit'] ?? null);
        $offset = $arguments['offset'] ?? 0;

        $entries = $this->normalizeEntries($this->getEntries($arguments));
        $paginatedEntries = array_slice($entries, $offset, $limit);

        $formatted = $this->formatter->formatList(
            $paginatedEntries,
            $this->getListFields()
        );

        $paginatedData = $this->pagination->paginate(
            $formatted,
            count($entries),
            $limit,
            $offset
        );

        return $this->formatResponse(
            json_encode($paginatedData, JSON_PRETTY_PRINT),
            $paginatedData
        );
    }

    /**
     * Get detailed view of a single item.
     */
    public function detail(string $id, array $arguments = []): array
    {
        $entry = $this->storage->find($id);

        if (! $entry) {
            return $this->formatError("Entry not found: {$id}");
        }

        return $this->formatter->formatDetail($entry->toArray());
    }

    /**
     * Get statistics about the data.
     */
    public function stats(array $arguments = []): array
    {
        $entries = $this->normalizeEntries($this->getEntries($arguments));

        return $this->formatter->formatStats(
            $this->calculateStats($entries)
        );
    }

    /**
     * Search entries.
     */
    public function search(array $arguments = []): array
    {
        $query = $arguments['query'] ?? '';
        $limit = $this->pagination->getLimit($arguments['limit'] ?? null);

        $entries = $this->searchEntries($query, $arguments);

        $paginatedData = $this->pagination->paginate(
            $this->formatter->formatList($entries, $this->getListFields()),
            count($entries),
            $limit,
            0
        );

        return $this->formatResponse(
            json_encode($paginatedData, JSON_PRETTY_PRINT),
            $paginatedData
        );
    }

    /**
     * Get entries from storage.
     */
    protected function getEntries(array $arguments = []): array
    {
        $fetchLimit = isset($arguments['period']) ? 10000 : ($arguments['limit'] ?? 100);

        $queryOptions = (new EntryQueryOptions())
            ->limit($fetchLimit);

        if (isset($arguments['tag'])) {
            $queryOptions->tag($arguments['tag']);
        }

        if (isset($arguments['family_hash'])) {
            $queryOptions->familyHash($arguments['family_hash']);
        }

        if (isset($arguments['before'])) {
            $queryOptions->beforeSequence($arguments['before']);
        }

        $entries = iterator_to_array($this->storage->get(
            $this->entryType,
            $queryOptions
        ));

        // Filter by period if specified
        if (isset($arguments['period'])) {
            $cutoffTime = $this->getPeriodCutoffTime($arguments['period']);
            $entries = array_filter($entries, function ($entry) use ($cutoffTime) {
                $createdAt = $entry->createdAt ?? null;
                if (! $createdAt) {
                    return false;
                }

                $entryTimestamp = method_exists($createdAt, 'timestamp')
                    ? $createdAt->timestamp
                    : strtotime((string) $createdAt);

                return $entryTimestamp >= $cutoffTime;
            });
            $entries = array_values($entries);
        }

        return $entries;
    }

    /**
     * Get cutoff timestamp for period filter.
     */
    protected function getPeriodCutoffTime(string $period): int
    {
        $now = time();

        return match ($period) {
            '5m' => $now - (5 * 60),
            '15m' => $now - (15 * 60),
            '1h' => $now - (60 * 60),
            '6h' => $now - (6 * 60 * 60),
            '24h' => $now - (24 * 60 * 60),
            '7d' => $now - (7 * 24 * 60 * 60),
            '14d' => $now - (14 * 24 * 60 * 60),
            '21d' => $now - (21 * 24 * 60 * 60),
            '30d' => $now - (30 * 24 * 60 * 60),
            '3M' => $now - (90 * 24 * 60 * 60),
            '6M' => $now - (180 * 24 * 60 * 60),
            '12M' => $now - (365 * 24 * 60 * 60),
            default => $now - (60 * 60),
        };
    }

    /**
     * Search entries with query and filters.
     */
    protected function searchEntries(string $query, array $filters): array
    {
        $entries = $this->getEntries($filters);

        if (empty($query)) {
            return $entries;
        }

        return array_filter($entries, function ($entry) use ($query) {
            $searchableContent = $this->getSearchableContent($this->normalizeEntry($entry));

            return stripos($searchableContent, $query) !== false;
        });
    }

    /**
     * Get searchable content from an entry.
     */
    protected function getSearchableContent(array $entry): string
    {
        $fields = $this->getSearchableFields();
        $content = [];

        foreach ($fields as $field) {
            if (isset($entry['content'][$field])) {
                $value = $entry['content'][$field];
                $content[] = is_array($value) ? json_encode($value) : (string) $value;
            }
        }

        return implode(' ', $content);
    }

    /**
     * Calculate statistics for entries.
     */
    protected function calculateStats(array $entries): array
    {
        if (empty($entries)) {
            return [
                'count' => 0,
                'avg_duration' => 0,
                'min_duration' => 0,
                'max_duration' => 0,
            ];
        }

        $durations = array_map(function ($entry) {
            return $entry['content']['duration'] ?? 0;
        }, $entries);

        return [
            'count' => count($entries),
            'avg_duration' => array_sum($durations) / count($durations),
            'min_duration' => min($durations),
            'max_duration' => max($durations),
            'p50' => $this->percentile($durations, 50),
            'p95' => $this->percentile($durations, 95),
            'p99' => $this->percentile($durations, 99),
        ];
    }

    /**
     * Calculate percentile value.
     */
    protected function percentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $index = ceil(($percentile / 100) * count($values)) - 1;

        return $values[$index] ?? 0;
    }

    /**
     * Get fields to include in list view.
     */
    abstract protected function getListFields(): array;

    /**
     * Get searchable fields.
     */
    abstract protected function getSearchableFields(): array;

    /**
     * Normalize entry to array format.
     */
    protected function normalizeEntry(mixed $entry): array
    {
        if (is_array($entry)) {
            return $entry;
        }

        if (is_object($entry)) {
            $content = isset($entry->content) && is_array($entry->content) ? $entry->content : [];
            $id = $entry->id ?? null;
            $createdAt = $entry->createdAt ?? null;

            $createdAtString = $createdAt ? (string) $createdAt : null;

            return [
                'id' => $id,
                'content' => $content,
                'created_at' => $createdAtString,
            ];
        }

        return ['id' => null, 'content' => [], 'created_at' => null];
    }

    /**
     * Normalize entries array.
     */
    protected function normalizeEntries(array $entries): array
    {
        return array_map(fn ($e) => $this->normalizeEntry($e), $entries);
    }
}
