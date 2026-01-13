<?php

declare(strict_types=1);

namespace Skylence\TelescopeMcp\MCP\Tools\Adapters;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Skylence\TelescopeMcp\MCP\Tools\AbstractTool;

/**
 * Base adapter to bridge existing AbstractTool implementations
 * to Laravel's MCP Tool interface.
 *
 * This allows us to support both stdio (via Laravel MCP) and HTTP
 * access methods without duplicating tool logic.
 */
abstract class AbstractToolAdapter extends Tool
{
    /**
     * Get the wrapped AbstractTool instance.
     */
    abstract protected function getTool(): AbstractTool;

    /**
     * The tool's name (derived from wrapped tool).
     */
    public function name(): string
    {
        return $this->getTool()->getShortName();
    }

    /**
     * The tool's description (derived from wrapped tool).
     */
    protected string $description;

    /**
     * Constructor to set description from wrapped tool.
     */
    public function __construct()
    {
        $schema = $this->getTool()->getSchema();
        $this->description = $schema['description'] ?? 'Telescope MCP tool';
    }

    /**
     * Get the tool's input schema.
     */
    public function schema(JsonSchema $schema): array
    {
        $toolSchema = $this->getTool()->getSchema();
        $inputSchema = $toolSchema['inputSchema'] ?? ['type' => 'object', 'properties' => []];

        // Convert JSON schema to Laravel's schema builder format
        $result = [];
        $properties = $inputSchema['properties'] ?? [];
        $required = $inputSchema['required'] ?? [];

        foreach ($properties as $propertyName => $propertyDef) {
            $result[$propertyName] = $this->convertProperty($schema, $propertyDef, in_array($propertyName, $required));
        }

        return $result;
    }

    /**
     * Convert a JSON schema property to Laravel's schema format.
     */
    private function convertProperty(JsonSchema $schema, array $propertyDef, bool $required)
    {
        $type = $propertyDef['type'] ?? 'string';
        $description = $propertyDef['description'] ?? '';
        $default = $propertyDef['default'] ?? null;
        $enum = $propertyDef['enum'] ?? null;

        // Create base schema based on type
        $schemaProperty = match ($type) {
            'string' => $schema->string(),
            'integer', 'number' => $schema->integer(),
            'boolean' => $schema->boolean(),
            'array' => $schema->array(),
            'object' => $schema->object(),
            default => $schema->string(),
        };

        // Add description
        if ($description) {
            $schemaProperty = $schemaProperty->description($description);
        }

        // Add enum values
        if ($enum && is_array($enum)) {
            $schemaProperty = $schemaProperty->enum($enum);
        }

        // Add default value (only if not required)
        if (!$required && $default !== null) {
            $schemaProperty = $schemaProperty->default($default);
        }

        return $schemaProperty;
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        try {
            $params = $request->all();
            $result = $this->getTool()->execute($params);

            // Extract text content from result
            $text = '';
            if (isset($result['content']) && is_array($result['content'])) {
                foreach ($result['content'] as $content) {
                    if (isset($content['text'])) {
                        $text .= $content['text'];
                    }
                }
            }

            // Return JSON response with the data
            if (isset($result['data'])) {
                return Response::json($result['data'])->text($text);
            }

            // Return text-only response
            return Response::text($text);
        } catch (\Exception $e) {
            return Response::text("Error executing tool: {$e->getMessage()}");
        }
    }
}
