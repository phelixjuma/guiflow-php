<?php

namespace PhelixJuma\GUIFlow\Observability\Backends;

use Aws\DynamoDb\DynamoDbClient;
use Exception;
use PhelixJuma\GUIFlow\Observability\ObservabilityService;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

class DynamoDbBackend implements BackendInterface
{
    private ?DynamoDbClient $dynamoDb = null;
    private array $config;
    private array $models;

    public function __construct(array $config, array $models)
    {
        $this->config = $config;
        $this->models = $models;
    }

    private function initializeClient(): void
    {
        if ($this->dynamoDb === null) {
            $this->dynamoDb = new DynamoDbClient($this->config);
        }
    }

    /**
     * @throws Exception
     */
    private function ensureTableExists(string $tableName): void
    {
        $this->initializeClient();

        $existingTables = $this->dynamoDb->listTables()['TableNames'];
        if (!in_array($tableName, $existingTables)) {
            if (isset($this->models[$tableName])) {
                $this->createTable($tableName, $this->models[$tableName]);
            } else {
                throw new Exception("Table $tableName does not exist, and no model is defined to create it.");
            }
        }
    }

    /**
     * @throws ReflectionException
     */
    private function createTable(string $tableName, string $modelClass): void
    {
        $schema = $this->generateSchemaFromModel($modelClass);

        $this->dynamoDb->createTable([
            'TableName' => $tableName,
            'AttributeDefinitions' => $schema['attributes'],
            'KeySchema' => $schema['keySchema'],
            'BillingMode' => 'PAY_PER_REQUEST',
        ]);

        $this->dynamoDb->waitUntil('TableExists', ['TableName' => $tableName]);
    }

    /**
     * @throws ReflectionException
     */
    private function generateSchemaFromModel(string $modelClass): array
    {
        $reflection = new ReflectionClass($modelClass);
        $properties = $reflection->getProperties();

        $attributes = [];
        $keySchema = [];
        foreach ($properties as $property) {
            $name = $property->getName();
            $type = $this->getPropertyType($property);

            if (empty($keySchema)) {
                $keySchema[] = [
                    'AttributeName' => $name,
                    'KeyType' => 'HASH',
                ];
            }

            $attributes[] = [
                'AttributeName' => $name,
                'AttributeType' => $this->mapPhpTypeToDynamoDbType($type),
            ];
        }

        return [
            'attributes' => $attributes,
            'keySchema' => $keySchema,
        ];
    }

    private function getPropertyType(ReflectionProperty $property): string
    {
        $type = $property->getType();
        return $type ? $type->getName() : 'string';
    }

    private function mapPhpTypeToDynamoDbType(string $phpType): string
    {
        return match ($phpType) {
            'int', 'integer' => 'N',
            'float', 'double' => 'N',
            'bool', 'boolean' => 'BOOL',
            'array' => 'S',
            'string' => 'S',
            default => 'S',
        };
    }

    /**
     * @throws Exception
     */
    public function create(string $tableName, array $data): void
    {
        $this->ensureTableExists($tableName);

        $item = array_map(fn($value) => $this->mapValueToDynamoDbType($value), $data);
        $this->dynamoDb->putItem([
            'TableName' => $tableName,
            'Item' => $item,
        ]);

        if (!empty($data['parent_execution_id'])) {
            $this->linkParentAndChild($tableName, $data['parent_execution_id'], $data['execution_id']);
        }
    }

    /**
     * @throws Exception
     */
    public function update(string $tableName, string $executionId, array $updates): void
    {
        $this->ensureTableExists($tableName);

        $updateExpression = 'SET ' . implode(', ', array_map(fn($k) => "#$k = :$k", array_keys($updates)));
        $expressionAttributeNames = array_combine(
            array_map(fn($k) => "#$k", array_keys($updates)),
            array_keys($updates)
        );
        $expressionAttributeValues = array_combine(
            array_map(fn($k) => ":$k", array_keys($updates)),
            array_map(fn($v) => $this->mapValueToDynamoDbType($v), array_values($updates))
        );

        $this->dynamoDb->updateItem([
            'TableName' => $tableName,
            'Key' => ['execution_id' => ['S' => $executionId]],
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeNames' => $expressionAttributeNames,
            'ExpressionAttributeValues' => $expressionAttributeValues,
        ]);
    }

    /**
     * Links a child execution to its parent.
     */
    private function linkParentAndChild(string $childTable, string $parentExecutionId, string $childExecutionId): void
    {
        $parentTable = $childTable === ObservabilityService::WORKFLOW_TASK_EXECUTION_TABLE
            ? ObservabilityService::WORKFLOW_EXECUTION_TABLE
            : ObservabilityService::WORKFLOW_TASK_EXECUTION_TABLE;

        $this->dynamoDb->updateItem([
            'TableName' => $parentTable,
            'Key' => ['execution_id' => ['S' => $parentExecutionId]],
            'UpdateExpression' => 'ADD children :child',
            'ExpressionAttributeValues' => [
                ':child' => ['SS' => [$childExecutionId]],
            ],
        ]);
    }

    public function getExecutionById(string $executionId, string $tableName = ObservabilityService::WORKFLOW_EXECUTION_TABLE): ?array
    {
        $this->initializeClient();

        $result = $this->dynamoDb->getItem([
            'TableName' => $tableName,
            'Key' => ['execution_id' => ['S' => $executionId]],
        ]);

        if (empty($result['Item'])) {
            return null;
        }

        $executionData = $this->mapDynamoDbItemToArray($result['Item']);
        $executionData['children'] = $this->fetchChildrenRecursively($executionId, $tableName);

        return $executionData;
    }

    private function fetchChildrenRecursively(string $executionId, string $tableName): array
    {
        $children = [];

        $result = $this->dynamoDb->scan([
            'TableName' => $tableName,
            'FilterExpression' => 'parent_execution_id = :parentId',
            'ExpressionAttributeValues' => [
                ':parentId' => ['S' => $executionId],
            ],
        ]);

        foreach ($result['Items'] as $childItem) {
            $childData = $this->mapDynamoDbItemToArray($childItem);
            $childData['children'] = $this->fetchChildrenRecursively($childData['execution_id'], $tableName);
            $children[] = $childData;
        }

        return $children;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    private function mapValueToDynamoDbType($value): array
    {
        if (is_int($value) || is_float($value)) {
            return ['N' => (string)$value];
        }
        if (is_bool($value)) {
            return ['BOOL' => $value];
        }
        if (is_array($value)) {
            return ['S' => json_encode($value)];
        }
        return ['S' => (string)$value];
    }

    private function mapDynamoDbItemToArray(array $item): array
    {
        $mapped = [];
        foreach ($item as $key => $value) {
            $mapped[$key] = reset($value);
        }
        return $mapped;
    }
}
