<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\Services;

class PaginationManager
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get the limit for pagination.
     */
    public function getLimit(?int $requestedLimit = null): int
    {
        $default = $this->config['default'] ?? 10;
        $maximum = $this->config['maximum'] ?? 25;

        if ($requestedLimit === null) {
            return $default;
        }

        return min($requestedLimit, $maximum);
    }

    /**
     * Paginate data with metadata.
     */
    public function paginate(array $data, int $total, int $limit, int $offset = 0): array
    {
        $hasMore = ($offset + $limit) < $total;
        $nextCursor = $hasMore ? $this->encodeCursor($offset + $limit) : null;
        $prevCursor = $offset > 0 ? $this->encodeCursor(max(0, $offset - $limit)) : null;

        return [
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
                'prev_cursor' => $prevCursor,
                'current_page' => (int) floor($offset / $limit) + 1,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    /**
     * Encode cursor for pagination.
     */
    public function encodeCursor($value): string
    {
        return base64_encode(json_encode([
            'value' => $value,
            'timestamp' => time(),
        ]));
    }

    /**
     * Decode cursor for pagination.
     */
    public function decodeCursor(string $cursor): ?array
    {
        try {
            $decoded = base64_decode($cursor);

            return json_decode($decoded, true);
        } catch (\Exception $e) {
            return null;
        }
    }
}
