<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools;

abstract class AbstractTool
{
    /**
     * Get the tool's short name (used as identifier).
     */
    abstract public function getShortName(): string;

    /**
     * Get the tool's full name.
     */
    public function getName(): string
    {
        return 'mcp__telescope-mcp__'.$this->getShortName();
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array{name: string, description: string, inputSchema: array}
     */
    abstract public function getSchema(): array;

    /**
     * Execute the tool with given parameters.
     */
    abstract public function execute(array $params): array;

    /**
     * Format a successful response.
     */
    protected function formatResponse(string $text, mixed $data = null): array
    {
        $response = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text,
                ],
            ],
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $response;
    }

    /**
     * Format an error response.
     */
    protected function formatError(string $message): array
    {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Error: {$message}",
                ],
            ],
            'isError' => true,
        ];
    }
}
