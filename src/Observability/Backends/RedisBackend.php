<?php

namespace PhelixJuma\GUIFlow\Observability\Backends;

use Exception;
use PhelixJuma\GUIFlow\Observability\ObservabilityService;
use Predis\Client;
use ReflectionClass;
use ReflectionException;
use OpenSwoole\Coroutine\Redis;

class RedisBackend implements BackendInterface
{
    private ?Client $redis = null;
    private array $config;
    private array $models;

    /**
     * Store configuration and models for later use, but do not initialize the Redis client.
     */
    public function __construct(array $config, array $models)
    {
        $this->config = $config;
        $this->models = $models;
    }

    /**
     * Lazily initialize the Redis client.
     */
    private function initializeClient(): void
    {
        if ($this->redis === null) {
            $this->redis = new Client($this->config);
        }
    }

    /**
     * Ensures the "table" (key prefix) is valid before performing any operations.
     *
     * @param string $tableName The table name (key prefix).
     * @throws ReflectionException|Exception
     */
    private function ensureTableExists(string $tableName): void
    {
        if (!isset($this->models[$tableName])) {
            throw new Exception("Table $tableName is not defined in the models.");
        }
    }

    /**
     * Saves data to Redis after type conversion, ensuring the table exists.
     * @throws ReflectionException
     */
    public function create(string $tableName, array $data): void
    {
        $this->initializeClient();
        $this->ensureTableExists($tableName);

        // Save the current execution
        $key = "{$tableName}:{$data['execution_id']}";
        $convertedData = $this->mapValuesToRedisFormat($data);
        $this->redis->hmset($key, $convertedData);

        // Handle parent-child relationships dynamically
        if (!empty($data['parent_execution_id'])) {
            $this->linkParentAndChild($tableName, $data['parent_execution_id'], $data['execution_id']);
        }
    }

    /**
     * Links the parent and child dynamically based on the table type.
     *
     * @param string $childTable The table name where the child is stored.
     * @param string $parentExecutionId The ID of the parent execution.
     * @param string $childExecutionId The ID of the child execution.
     */
    private function linkParentAndChild(string $childTable, string $parentExecutionId, string $childExecutionId): void
    {
        $parentTable = $childTable === ObservabilityService::WORKFLOW_TASK_EXECUTION_TABLE
            ? ObservabilityService::WORKFLOW_EXECUTION_TABLE
            : ObservabilityService::WORKFLOW_TASK_EXECUTION_TABLE;

        $parentKey = "{$parentTable}:{$parentExecutionId}:children";

        $this->redis->sadd($parentKey, [$childExecutionId]);
    }

    /**
     * Updates data in Redis after type conversion, ensuring the table exists.
     * @throws ReflectionException
     */
    public function update(string $tableName, string $executionId, array $updates): void
    {
        $this->initializeClient();
        $this->ensureTableExists($tableName);

        $key = "{$tableName}:{$executionId}";
        $convertedUpdates = $this->mapValuesToRedisFormat($updates);
        $this->redis->hmset($key, $convertedUpdates);
    }

    /**
     * Retrieves a single execution by its ID.
     * @throws ReflectionException
     */
    public function get(string $tableName, string $executionId, string $modelClass): ?object
    {
        $this->initializeClient();
        $this->ensureTableExists($tableName);

        $key = "{$tableName}:{$executionId}";
        $data = $this->redis->hgetall($key);

        if (empty($data)) {
            return null;
        }

        return $this->mapRedisValuesToModel($data, $modelClass);
    }

    /**
     * Retrieves a workflow or task execution along with all its children.
     *
     * @param string $executionId The execution ID.
     * @param string $tableName The table where the execution is stored.
     * @return array|null The execution and its children.
     */
    public function getExecutionById(string $executionId, string $tableName = ObservabilityService::WORKFLOW_EXECUTION_TABLE): ?array
    {
        $this->initializeClient();

        $executionKey = "{$tableName}:{$executionId}";
        $executionData = $this->redis->hgetall($executionKey);

        if (empty($executionData)) {
            return null; // Execution not found
        }

        $executionData['children'] = $this->fetchChildrenRecursively($executionId, $tableName);

        return $executionData;
    }

    private function fetchChildrenRecursively(string $executionId, string $tableName): array
    {
        $childrenKey = "{$tableName}:{$executionId}:children";
        $childIds = $this->redis->smembers($childrenKey);
        $children = [];

        foreach ($childIds as $childId) {
            $childTable = $tableName === ObservabilityService::WORKFLOW_EXECUTION_TABLE
                ? ObservabilityService::WORKFLOW_TASK_EXECUTION_TABLE
                : ObservabilityService::WORKFLOW_EXECUTION_TABLE;

            $childData = $this->redis->hgetall("{$childTable}:{$childId}");
            if (!empty($childData)) {
                $childData['children'] = $this->fetchChildrenRecursively($childId, $childTable);
                $children[] = $childData;
            }
        }

        return $children;
    }

    /**
     * Maps PHP values to Redis-compatible formats (e.g., JSON for arrays).
     */
    private function mapValuesToRedisFormat(array $data): array
    {
        return array_filter(array_map(function ($value) {
            if (is_array($value) || is_object($value)) {
                return json_encode($value); // Serialize arrays to JSON
            }
            if (is_bool($value)) {
                return $value ? '1' : '0'; // Store booleans as strings
            }
            return (string)$value; // Store other types as strings
        }, $data), function ($value) {
            return !empty($value); // Remove keys with empty values
        });
    }

    /**
     * Maps Redis-stored values back to the appropriate PHP types.
     *
     * @throws ReflectionException
     */
    private function mapRedisValuesToModel(array $data, string $modelClass): object
    {
        $reflection = new ReflectionClass($modelClass);
        $object = $reflection->newInstanceWithoutConstructor();

        foreach ($data as $key => $value) {
            if (!$reflection->hasProperty($key)) {
                continue; // Skip keys that don't exist in the model
            }

            $property = $reflection->getProperty($key);
            $type = $property->getType();

            if ($type) {
                $phpType = $type->getName();
                $property->setAccessible(true);

                $property->setValue($object, $this->mapRedisValueToPhpType($value, $phpType));
            }
        }

        return $object;
    }

    /**
     * Converts Redis values to appropriate PHP types based on the model property type.
     */
    private function mapRedisValueToPhpType(string $value, string $phpType): mixed
    {
        return match ($phpType) {
            'int', 'integer' => (int)$value,
            'float', 'double' => (float)$value,
            'bool', 'boolean' => $value === '1',
            'array' => json_decode($value, true) ?? [],
            'string' => $value,
            default => $value,
        };
    }

    /**
     * Returns the backend configuration for serialization.
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
