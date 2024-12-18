<?php

namespace PhelixJuma\GUIFlow\Utils;

use OpenSwoole\Process\Pool;
use OpenSwoole\Table;
use OpenSwoole\Util;
use OpenSwoole\Atomic;
use OpenSwoole\Channel;

class Parallel {

    /**
     * Parallel batch processing using OpenSwoole's Process Pool
     *
     * @param array $tasks
     * @param int|null $workerNum
     * @param string|null $batchId
     * @return array
     */
    public static function parallelBatch(array $tasks, int $workerNum = null, $batchId = null): array
    {
        if (empty($tasks)) {
            return [];
        }

        $batchId = $batchId ?: Randomiser::getRandomString(5);
        $workerNum = min($workerNum ?: Util::getCPUNum(), count($tasks));
        $totalTasks = count($tasks);

        echo "\n[Batch-{$batchId}] Starting batch processing with {$totalTasks} tasks using {$workerNum} workers\n";

        // Create atomic counters and control mechanisms
        $completedTasks = new Atomic(0);
        $activeWorkers = new Atomic($workerNum);
        $isShuttingDown = new Atomic(0);
        $shutdownCompleted = new Channel(1);

        // Create atomic locks for tasks
        $taskLocks = [];
        for ($i = 0; $i < count($tasks); $i++) {
            $taskLocks[$i] = new Atomic(0); // 0 = unclaimed, 1 = claimed
        }

        // Shared memory for task management
        $taskTable = new Table(count($tasks));
        $taskTable->column('index', Table::TYPE_INT, 4);
        $taskTable->column('status', Table::TYPE_INT, 1);    // 0=pending, 1=processing, 2=completed
        $taskTable->column('has_error', Table::TYPE_INT, 1); // 0=no error, 1=has error
        $taskTable->create();

        // Results table
        $resultTable = new Table(count($tasks));
        $resultTable->column('data', Table::TYPE_STRING, 65536); // Adjust size if needed
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
        use ($taskTable, $resultTable, $batchId, $completedTasks, $activeWorkers, $totalTasks,
            $tasks, $taskLocks, $isShuttingDown, $shutdownCompleted) {

            // Check if we're in shutdown mode
            if ($isShuttingDown->get() === 1) {
                echo "[Batch-{$batchId}] Worker-{$workerId} skipped (shutdown in progress)\n";
                exit(0);
            }

            echo "[Batch-{$batchId}] Worker-{$workerId} started\n";

            while ($completedTasks->get() < $totalTasks) {
                // Find and claim an unclaimed task
                $currentTaskIndex = null;

                // Try to claim a task using atomic operations
                foreach ($taskLocks as $index => $lock) {
                    // Only try to claim tasks that aren't completed
                    if ($taskTable->get((string)$index)['status'] !== 2) {
                        // Attempt to claim task atomically
                        if ($lock->cmpset(0, 1)) {
                            $currentTaskIndex = $index;
                            $taskTable->set((string)$index, [
                                'index' => $index,
                                'status' => 1, // Mark as processing
                                'has_error' => 0
                            ]);
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
                    echo "[Batch-{$batchId}] Worker-{$workerId} completed task {$currentTaskIndex} successfully. " .
                        "Completed tasks: {$completedTasks->get()}/{$totalTasks}\n";
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

            // Decrement active workers
            $remainingWorkers = $activeWorkers->sub(1);
            echo "[Batch-{$batchId}] Worker-{$workerId} finished. Remaining workers: " . max(0, $remainingWorkers) . "\n";

            // Last worker initiates shutdown
            if ($remainingWorkers === 0 && $isShuttingDown->cmpset(0, 1)) {
                echo "[Batch-{$batchId}] All workers completed. Initiating pool shutdown\n";
                $shutdownCompleted->push(true);
                $pool->shutdown();
            }

            exit(0);
        });

        echo "[Batch-{$batchId}] Starting process pool\n";

        // Start the pool in a separate process
        $process = new Process(function() use ($pool) {
            $pool->start();
        });
        $process->start();

        // Wait for shutdown signal
        $shutdownCompleted->pop();

        echo "[Batch-{$batchId}] Pool has completed execution\n";

        // Collect results
        echo "[Batch-{$batchId}] Collecting results\n";
        $finalResults = [];
        foreach ($tasks as $index => $_) {
            $data = $resultTable->get((string)$index);
            if ($data) {
                $finalResults[$index] = unserialize($data['data']);
            } else {
                $finalResults[$index] = ['error' => 'Task result not found'];
            }
        }

        echo "[Batch-{$batchId}] Batch processing completed\n";
        return $finalResults;
    }

}
