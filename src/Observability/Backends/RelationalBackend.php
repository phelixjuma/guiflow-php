<?php

namespace PhelixJuma\GUIFlow\Observability\Backends;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Exception;
use PhelixJuma\GUIFlow\Observability\ObservabilityService;
use ReflectionClass;
use ReflectionException;

class RelationalBackend implements BackendInterface
{
    private ?Connection $connection = null;
    private array $config;
    private array $models;

    public function __construct(array $config, array $models)
    {
        $this->config = $config;
        $this->models = $models;
    }

    private function initializeConnection(): void
    {
        if ($this->connection === null) {
            $this->connection = DriverManager::getConnection($this->config);
        }
    }

    /**
     * @throws Exception|ReflectionException
     * @throws \Exception
     */
    private function ensureTableExists(string $tableName): void
    {
        $this->initializeConnection();

        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([$tableName])) {
            if (isset($this->models[$tableName])) {
                $this->createTable($tableName, $this->models[$tableName]);
            } else {
                throw new \Exception("Table $tableName does not exist, and no model is defined to create it.");
            }
        }
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    private function createTable(string $tableName, string $modelClass): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $table = new Table($tableName);
        $schema = $this->generateSchemaFromModel($modelClass);

        foreach ($schema['columns'] as $columnName => $columnType) {
            $table->addColumn($columnName, $columnType);
        }

        $table->setPrimaryKey([$schema['primaryKey']]);

        $schemaManager->createTable($table);
    }

    /**
     * @throws ReflectionException
     */
    private function generateSchemaFromModel(string $modelClass): array
    {
        $reflection = new ReflectionClass($modelClass);
        $properties = $reflection->getProperties();

        $columns = [];
        $primaryKey = null;

        foreach ($properties as $property) {
            $name = $property->getName();
            $type = $property->getType()?->getName() ?? 'string';

            if (!$primaryKey) {
                $primaryKey = $name;
            }

            $columns[$name] = $this->mapPhpTypeToDbType($type);
        }

        return [
            'columns' => $columns,
            'primaryKey' => $primaryKey,
        ];
    }

    private function mapPhpTypeToDbType(string $phpType): string
    {
        return match ($phpType) {
            'int', 'integer' => 'integer',
            'float', 'double' => 'float',
            'bool', 'boolean' => 'boolean',
            'array' => 'json',
            'string' => 'string',
            default => 'string',
        };
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function create(string $tableName, array $data): void
    {
        $this->ensureTableExists($tableName);

        $this->connection->insert($tableName, $this->mapValuesToDbFormat($data));

        if (!empty($data['parent_execution_id'])) {
            $this->linkParentAndChild($tableName, $data['parent_execution_id'], $data['execution_id']);
        }
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function update(string $tableName, string $executionId, array $updates): void
    {
        $this->ensureTableExists($tableName);

        $this->connection->update(
            $tableName,
            $this->mapValuesToDbFormat($updates),
            ['execution_id' => $executionId]
        );
    }

    private function mapValuesToDbFormat(array $data): array
    {
        return array_filter(
            array_map(function ($value) {
                if (is_array($value)) {
                    return json_encode($value);
                }
                return $value;
            }, $data),
            function ($value) {
                return !empty($value);
            }
        );
    }

    /**
     * @throws Exception
     */
    private function linkParentAndChild(string $childTable, string $parentExecutionId, string $childExecutionId): void
    {
        $parentTable = $childTable === ObservabilityService::WORKFLOW_TASK_EXECUTION_TABLE
            ? ObservabilityService::WORKFLOW_EXECUTION_TABLE
            : ObservabilityService::WORKFLOW_TASK_EXECUTION_TABLE;

        $parentExecution = $this->connection->fetchAssociative(
            "SELECT * FROM {$parentTable} WHERE execution_id = :execution_id",
            ['execution_id' => $parentExecutionId]
        );

        if ($parentExecution) {
            $children = json_decode($parentExecution['children'] ?? '[]', true);
            $children[] = $childExecutionId;

            $this->connection->update(
                $parentTable,
                ['children' => json_encode($children)],
                ['execution_id' => $parentExecutionId]
            );
        }
    }

    /**
     * @throws Exception
     */
    public function getExecutionById(string $executionId, string $tableName = ObservabilityService::WORKFLOW_EXECUTION_TABLE): ?array
    {
        $this->initializeConnection();

        $execution = $this->connection->fetchAssociative(
            "SELECT * FROM {$tableName} WHERE execution_id = :execution_id",
            ['execution_id' => $executionId]
        );

        if (!$execution) {
            return null;
        }

        $execution['children'] = $this->fetchChildrenRecursively($executionId, $tableName);

        return $execution;
    }

    /**
     * @throws Exception
     */
    private function fetchChildrenRecursively(string $executionId, string $tableName): array
    {
        $childTable = $tableName === ObservabilityService::WORKFLOW_EXECUTION_TABLE
            ? ObservabilityService::WORKFLOW_TASK_EXECUTION_TABLE
            : ObservabilityService::WORKFLOW_EXECUTION_TABLE;

        $children = $this->connection->fetchAllAssociative(
            "SELECT * FROM {$childTable} WHERE parent_execution_id = :parent_id",
            ['parent_id' => $executionId]
        );

        foreach ($children as &$child) {
            $child['children'] = $this->fetchChildrenRecursively($child['execution_id'], $childTable);
        }

        return $children;
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
