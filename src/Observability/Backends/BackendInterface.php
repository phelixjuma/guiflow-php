<?php

namespace PhelixJuma\GUIFlow\Observability\Backends;

interface BackendInterface
{
    public function getConfig(): array;
    public function create(string $tableName, array $data): void;
    public function update(string $tableName, string $executionId, array $updates): void;
    public function getExecutionById(string $executionId, string $tableName = ''): mixed;
}
