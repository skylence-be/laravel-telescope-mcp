<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

final class EchoTool extends AbstractTool
{
    /**
     * Get the tool's short name.
     */
    public function getShortName(): string
    {
        return 'echo';
    }

    /**
     * Get the tool's schema definition.
     */
    public function getSchema(): array
    {
        return [
            'name' => $this->getShortName(),
            'description' => 'Echo back any message you send',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'message' => [
                        'type' => 'string',
                        'description' => 'The message to echo back',
                    ],
                ],
                'required' => ['message'],
            ],
        ];
    }

    /**
     * Execute the echo tool.
     */
    public function execute(array $params): array
    {
        if (! isset($params['message'])) {
            return $this->formatError('The "message" parameter is required');
        }

        $message = $params['message'];

        return $this->formatResponse(
            "Echo: {$message}",
            [
                'original' => $message,
                'length' => strlen($message),
                'timestamp' => now()->toIso8601String(),
            ]
        );
    }
}
