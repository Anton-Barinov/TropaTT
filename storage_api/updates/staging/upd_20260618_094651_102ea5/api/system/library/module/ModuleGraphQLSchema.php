<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleGraphQLSchema
{
    /** @var array<string, array{types: string, resolvers: array<string, callable>}> */
    private array $schemas = [];

    /**
     * Register a GraphQL type extension from a module.
     */
    public function registerTypeExtension(string $moduleName, string $typeName, string $schemaString): void
    {
        if (!isset($this->schemas[$moduleName])) {
            $this->schemas[$moduleName] = ['types' => '', 'resolvers' => []];
        }
        $this->schemas[$moduleName]['types'] .= "\n# Module: {$moduleName}\n" . $schemaString . "\n";
    }

    /**
     * Register a resolver function for a module's GraphQL field.
     */
    public function registerResolver(string $moduleName, string $fieldName, callable $resolver): void
    {
        if (!isset($this->schemas[$moduleName])) {
            $this->schemas[$moduleName] = ['types' => '', 'resolvers' => []];
        }
        $this->schemas[$moduleName]['resolvers'][$fieldName] = $resolver;
    }

    /**
     * Build the complete type definitions string from all modules.
     */
    public function buildTypeDefs(): string
    {
        $schema = "# Core types\n";
        $schema .= "type Query { hello: String }\n";
        $schema .= "type Mutation { noop: Boolean }\n";

        foreach ($this->schemas as $moduleName => $moduleSchema) {
            if ($moduleSchema['types'] !== '') {
                $schema .= "\n# --- Module: {$moduleName} ---\n";
                $schema .= $moduleSchema['types'];
            }
        }

        return $schema;
    }

    /**
     * Build the complete resolvers map from all modules.
     * @return array<string, callable>
     */
    public function buildResolvers(): array
    {
        $resolvers = [
            'Query' => ['hello' => fn() => 'Hello from CRM'],
            'Mutation' => ['noop' => fn() => true],
        ];

        foreach ($this->schemas as $moduleName => $moduleSchema) {
            foreach ($moduleSchema['resolvers'] as $fieldName => $resolver) {
                $resolvers['Query'][$fieldName] = $resolver;
            }
        }

        return $resolvers;
    }

    /**
     * Remove all GraphQL registrations for a module.
     */
    public function unregisterModule(string $moduleName): void
    {
        unset($this->schemas[$moduleName]);
    }

    /** @return array<int, string> */
    public function getRegisteredModules(): array
    {
        return array_keys($this->schemas);
    }

    /**
     * Export the full GraphQL schema as SDL string.
     */
    public function exportSDL(): string
    {
        return $this->buildTypeDefs();
    }

    /**
     * Export schema as JSON (for compatibility with GraphQL tools).
     */
    public function exportJSON(): string
    {
        return json_encode([
            'types' => $this->buildTypeDefs(),
            'modules' => $this->getRegisteredModules(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
