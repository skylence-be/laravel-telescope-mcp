<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

final class NotificationsTool extends TelescopeAbstractTool
{
    protected string $entryType = 'notification';

    public function getShortName(): string
    {
        return 'notifications';
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getShortName(),
            'description' => 'View notifications sent via Telescope',
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
                        'description' => 'Search query',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    protected function getListFields(): array
    {
        return [
            'id',
            'content.notification',
            'content.channel',
            'created_at',
        ];
    }

    protected function getSearchableFields(): array
    {
        return [
            'notification',
            'channel',
        ];
    }

    public function stats(array $arguments = []): array
    {
        $entries = $this->normalizeEntries($this->getEntries($arguments));

        if (empty($entries)) {
            return $this->formatter->formatStats([]);
        }

        $notificationCounts = [];
        $channelCounts = [];

        foreach ($entries as $entry) {
            $notification = $entry['content']['notification'] ?? 'unknown';
            $channel = $entry['content']['channel'] ?? 'unknown';

            $notificationCounts[$notification] = ($notificationCounts[$notification] ?? 0) + 1;
            $channelCounts[$channel] = ($channelCounts[$channel] ?? 0) + 1;
        }

        arsort($notificationCounts);

        return $this->formatter->formatStats([
            'total_notifications' => count($entries),
            'by_notification' => array_slice($notificationCounts, 0, 10, true),
            'by_channel' => $channelCounts,
        ]);
    }
}
