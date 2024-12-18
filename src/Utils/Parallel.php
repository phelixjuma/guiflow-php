<?php

namespace PhelixJuma\GUIFlow\Utils;

use OpenSwoole\Process\Pool;
use OpenSwoole\Table;
use OpenSwoole\Util;
use OpenSwoole\Atomic;

class Parallel {

    /**
     * Parallel batch processing using OpenSwoole's Process Pool
     *
     * @param array $tasks
     * @param int|null $workerNum
     * @return array
     */
    public static function parallelBatch(array $tasks, int $workerNum = null): array
    {
        if (empty($tasks)) {
            return [];
        }

        $batchId = Randomiser::getRandomString(5);
        $workerNum = min($workerNum ?: Util::getCPUNum(), count($tasks));
        $totalTasks = count($tasks);

        echo "\n[Batch-{$batchId}] Starting batch processing with {$totalTasks} tasks using {$workerNum} workers\n";

        // Create atomic counters
        $completedTasks = new Atomic(0);
        $activeWorkers = new Atomic($workerNum);

        // Shared memory for task management
        $taskTable = new Table(count($tasks));
        $taskTable->column('index', Table::TYPE_INT, 4);
        $taskTable->column('status', Table::TYPE_INT, 1);
        $taskTable->column('has_error', Table::TYPE_INT, 1);
        $taskTable->create();

        // Results table
        $resultTable = new Table(count($tasks));
        $resultTable->column('data', Table::TYPE_STRING, 65536);
        $resultTable->create();

        echo "[Batch-{$batchId}] Initializing {$totalTasks} tasks in shared memory\n";
        foreach ($tasks as $index => $_) {
            $taskTable->set((string)$index, [
                'index' => $index,
                'status' => 0,
                'has_error' => 0
            ]);
        }

        // Create pool
        $pool = new Pool($workerNum);

        $pool->on("WorkerStart", function (Pool $pool, int $workerId)
        use ($taskTable, $resultTable, $batchId, $completedTasks, $activeWorkers, $totalTasks, $tasks) {

            echo "[Batch-{$batchId}] Worker-{$workerId} started\n";

            while ($completedTasks->get() < $totalTasks) {
                // Find and claim an unclaimed task
                $currentTaskIndex = null;

                foreach ($taskTable as $key => $row) {
                    if ($row['status'] === 0) {
                        if ($taskTable->cas($key, 'status', 0, 1)) {
                            $currentTaskIndex = $row['index'];
                            break;
                        }
                    }
                }

                if ($currentTaskIndex === null) {
                    if ($completedTasks->get() >= $totalTasks) {
                        break;
                    }
                    usleep(1000);
                    continue;
                }

                echo "[Batch-{$batchId}] Worker-{$workerId} starting task {$currentTaskIndex}\n";

                try {
                    $task = $tasks[$currentTaskIndex];
                    $result = $task();

                    $resultTable->set((string)$currentTaskIndex, [
                        'data' => serialize($result)
                    ]);

                    $taskTable->set((string)$currentTaskIndex, [
                        'index' => $currentTaskIndex,
                        'status' => 2,
                        'has_error' => 0
                    ]);

                    $completedTasks->add(1);
                    echo "[Batch-{$batchId}] Worker-{$workerId} completed task {$currentTaskIndex} successfully\n";
                } catch (\Throwable $e) {
                    $resultTable->set((string)$currentTaskIndex, [
                        'data' => serialize(['error' => $e->getMessage()])
                    ]);

                    $taskTable->set((string)$currentTaskIndex, [
                        'index' => $currentTaskIndex,
                        'status' => 2,
                        'has_error' => 1
                    ]);

                    $completedTasks->add(1);
                    echo "[Batch-{$batchId}] Worker-{$workerId} failed task {$currentTaskIndex}: {$e->getMessage()}\n";
                }
            }

            $activeWorkers->sub(1);
            echo "[Batch-{$batchId}] Worker-{$workerId} finished. Active workers: {$activeWorkers->get()}\n";

            // Last worker initiates shutdown
            if ($activeWorkers->get() === 0) {
                echo "[Batch-{$batchId}] All workers completed. Initiating pool shutdown\n";
                $pool->shutdown();
            }

            exit(0);
        });

        echo "[Batch-{$batchId}] Starting process pool\n";
        $pool->start();
        echo "[Batch-{$batchId}] Pool has completed execution\n";

        // Collect results
        echo "[Batch-{$batchId}] Collecting results\n";
        $finalResults = [];
        foreach ($tasks as $index => $_) {
            $data = $resultTable->get((string)$index);
            $finalResults[$index] = unserialize($data['data']);
        }

        echo "[Batch-{$batchId}] Batch processing completed\n";
        return $finalResults;
    }
}
