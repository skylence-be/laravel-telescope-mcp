<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\Services;

class ResponseFormatter
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Format response based on mode.
     */
    public function format(array $data, string $mode = 'standard'): array
    {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode($data, JSON_PRETTY_PRINT),
                ],
            ],
        ];
    }

    /**
     * Format summary response.
     */
    public function formatSummary(array $data): array
    {
        return $this->format([
            'mode' => 'summary',
            'summary' => [
                'total_count' => $data['total'] ?? 0,
                'type' => $data['type'] ?? 'unknown',
                'period' => $data['period'] ?? null,
            ],
            'stats' => $data['stats'] ?? [],
        ]);
    }

    /**
     * Format list response with specified fields.
     */
    public function formatList(array $entries, array $fields): array
    {
        return array_map(function ($entry) use ($fields) {
            $formatted = [];

            foreach ($fields as $field) {
                $formatted[$field] = $this->extractField($entry, $field);
            }

            if (isset($entry['id'])) {
                $formatted['id'] = $entry['id'];
            }

            return $formatted;
        }, $entries);
    }

    /**
     * Format detail view of single item.
     */
    public function formatDetail(array $entry): array
    {
        return $this->format([
            'entry' => $entry,
        ]);
    }

    /**
     * Format statistics response.
     */
    public function formatStats(array $stats): array
    {
        return $this->format([
            'statistics' => $stats,
        ]);
    }

    /**
     * Extract a field from an entry using dot notation.
     */
    protected function extractField(array $entry, string $field)
    {
        $keys = explode('.', $field);
        $value = $entry;

        foreach ($keys as $key) {
            if (! isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }
}
