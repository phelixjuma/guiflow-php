<?php

namespace PhelixJuma\GUIFlow\Observability;

use Exception;
use PhelixJuma\GUIFlow\Observability\Backends\BackendInterface;
use PhelixJuma\GUIFlow\Observability\Backends\RelationalBackend;
use PhelixJuma\GUIFlow\Observability\Backends\RedisBackend;
use PhelixJuma\GUIFlow\Observability\Backends\DynamoDbBackend;
use ReflectionClass;
use ReflectionException;
use OpenSwoole\Coroutine as Co;

class ObservabilityService
{
    const WORKFLOW_EXECUTION_TABLE = 'workflow_executions';
    const WORKFLOW_TASK_EXECUTION_TABLE = 'workflow_task_executions';

    private $backendName;
    private $backendConfigs;

    private BackendInterface $backend;
    private array $models;

    /**
     * ObservabilityService constructor.
     *
     * @param array $configs Array of configurations.
     * @param string $backendName Backend name (e.g., relational, redis, dynamodb).
     * @throws Exception
     */
    public function __construct(array $configs, string $backendName)
    {
        $this->backendName = $backendName;
        $this->backendConfigs = $configs[$backendName] ?? null;
        $this->models = $this->loadModelsFromFolder(__DIR__ . '/Models');
    }

    /**
     * Dynamically initializes the backend based on the provided backend name and configuration.
     * @return $this
     * @throws Exception
     */
    private function initializeBackend(): static
    {
        $this->backend = match (strtolower($this->backendName)) {
            'relational' => new RelationalBackend($this->backendConfigs, $this->models),
            'redis' => new RedisBackend($this->backendConfigs, $this->models),
            'dynamodb' => new DynamoDbBackend($this->backendConfigs, $this->models),
            default => throw new Exception("Unsupported backend: $this->backendName"),
        };

        return $this;
    }

    /**
     * @param string $executionId
     * @return mixed
     * @throws Exception
     */
    public function getExecutionById(string $executionId): mixed
    {
        return $this->initializeBackend()->backend->getExecutionById($executionId);
    }

    /**
     * Log a workflow execution asynchronously.
     */
    public function logWorkflowExecution($payload): void
    {
        $this->executeInBackground(function () use ($payload) {
            $this->initializeBackend()->backend->create(self::WORKFLOW_EXECUTION_TABLE, $payload);
        });
    }

    /**
     * Update a workflow execution asynchronously.
     */
    public function updateWorkflowExecution(string $executionId, $payload): void
    {
        $this->executeInBackground(function () use ($executionId, $payload) {
            $this->initializeBackend()->backend->update(self::WORKFLOW_EXECUTION_TABLE, $executionId, $payload);
        });
    }

    /**
     * Log a task execution asynchronously.
     */
    public function logTaskExecution($payload): void
    {
        $this->executeInBackground(function () use ($payload) {
            $this->initializeBackend()->backend->create(self::WORKFLOW_TASK_EXECUTION_TABLE, $payload);
        });
    }

    /**
     * Update a task execution asynchronously.
     */
    public function updateTaskExecution(string $executionId, $payload): void
    {
        $this->executeInBackground(function () use ($executionId, $payload) {
            $this->initializeBackend()->backend->update(self::WORKFLOW_TASK_EXECUTION_TABLE, $executionId, $payload);
        });
    }

    /**
     * Execute a task in the background.
     */
    private function executeInBackground(callable $callback): void
    {

        if (co::getCid() > 0) {
            co::create(function () use ($callback) {
                try {
                    $callback();
                } catch (\Throwable $e) {
                    // Log any errors during execution
                    error_log("Error in coroutine: " . $e->getMessage());
                }
            });
        } else {
            co::run(function() use(&$callback) {
                co::create(function () use ($callback) {
                    try {
                        $callback();
                    } catch (\Throwable $e) {
                        // Log any errors during execution
                        error_log("Error in coroutine: " . $e->getMessage());
                    }
                });
            });
        }
    }

    /**
     * Dynamically loads model classes from the "Models" folder.
     */
    private function loadModelsFromFolder(string $folderPath): array
    {
        $models = [];
        $files = glob($folderPath . '/*.php');

        foreach ($files as $file) {
            require_once $file;

            $className = $this->getClassNameFromFile($file);
            $tableName = $this->generateTableName($className);

            $models[$tableName] = $className;
        }

        return $models;
    }

    /**
     * Generates a table name from the class name.
     * @throws ReflectionException
     */
    private function generateTableName(string $className): string
    {
        $baseName = (new ReflectionClass($className))->getShortName();

        // Remove 'Model' suffix if it exists
        if (str_ends_with($baseName, 'Model')) {
            $baseName = substr($baseName, 0, -5);
        }

        // Convert CamelCase to snake_case
        $snakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $baseName));

        // Pluralize by appending 's'
        return $snakeCase . 's';
    }

    /**
     * Extracts the fully qualified class name from a file.
     */
    private function getClassNameFromFile(string $filePath): string
    {
        $contents = file_get_contents($filePath);
        preg_match('/namespace\s+(.+?);/', $contents, $namespaceMatches);
        preg_match('/class\s+(\w+)/', $contents, $classMatches);

        $namespace = $namespaceMatches[1] ?? '';
        $className = $classMatches[1] ?? '';

        return $namespace ? $namespace . '\\' . $className : $className;
    }
}
