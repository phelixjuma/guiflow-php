<?php

namespace PhelixJuma\GUIFlow\Utils;

use OpenSwoole\Process\Pool;
use OpenSwoole\Table;
use OpenSwoole\Util;

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

        // Limit worker count to the number of tasks
        $workerNum = min($workerNum ?: Util::getCPUNum(), count($tasks));
        echo "\nNumber of workers in batch $batchId: $workerNum\n";

        // Shared memory table for task management
        $table = new Table(count($tasks) + 1); // +1 for a "done" flag
        $table->column('task', Table::TYPE_STRING, 65536);
        $table->column('done', Table::TYPE_INT, 1);
        $table->create();

        // Add tasks to the table
        foreach ($tasks as $index => $task) {
            $table->set((string)$index, ['task' => json_encode(['index' => $index, 'task' => $task])]);
        }
        $table->set('done', ['done' => 0]);

        // Process pool
        $pool = new Pool($workerNum);

        $pool->on("WorkerStart", function (Pool $pool, int $workerId) use ($table, $batchId) {
            echo "\nBatch $batchId Worker#{$workerId} started\n";

            while (true) {
                $currentTask = null;

                // Fetch a task from the table
                foreach ($table as $key => $row) {
                    if ($key !== 'done') { // Skip the "done" flag
                        $currentTask = json_decode($row['task'], true);
                        $table->del($key); // Mark task as taken
                        break;
                    }
                }

                // No more tasks
                if (!$currentTask) {
                    echo "\nQueue empty. Worker#{$workerId} exiting.\n";
                    break;
                }

                $taskIndex = $currentTask['index'];

                try {
                    echo "\nWorker#{$workerId} executing task $taskIndex\n";
                    $result = [$taskIndex]; // Simulated task result
                    $table->set("task_$taskIndex", ['task' => json_encode($result)]);
                    echo "\nWorker#{$workerId} completed task $taskIndex\n";
                } catch (\Throwable $e) {
                    $table->set("task_$taskIndex", ['task' => "Error: " . $e->getMessage()]);
                    echo "\nWorker#{$workerId} error on task $taskIndex: {$e->getMessage()}\n";
                }
            }

            echo "\nBatch $batchId Worker#{$workerId} exiting\n";
            exit(0); // Clean worker exit
        });

        $pool->on("WorkerStop", function ($pool, int $workerId) use ($batchId, $table) {
            echo "\nBatch $batchId Worker#{$workerId} stopped\n";

            // Check if all workers have exited and set the done flag
            if ($table->count() === 1 && $table->get('done') !== null) { // Only "done" key remains
                $table->set('done', ['done' => 1]);
            }
        });

        // Start the pool
        $pool->start();

        // Wait for completion flag
        while ($table->get('done')['done'] !== 1) {
            usleep(1000); // Small delay to avoid busy waiting
        }

        // Collect results
        echo "\nCollecting results\n";
        $results = [];
        foreach ($tasks as $index => $task) {
            $data = $table->get("task_$index");
            if ($data) {
                $results[$index] = json_decode($data['task'], true);
            }
        }

        echo "\nReturning results\n";
        return $results;
    }

}
