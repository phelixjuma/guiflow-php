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

        // Shared memory table for inter-process communication
        $table = new Table(count($tasks));
        $table->column('data', Table::TYPE_STRING, 65536);
        $table->create();

        // Shared task queue
        $taskQueue = new \SplQueue();
        foreach ($tasks as $index => $task) {
            $taskQueue->enqueue(['index' => $index, 'task' => $task]);
        }

        // Process pool
        $pool = new Pool($workerNum);

        $pool->on("WorkerStart", function ($pool, int $workerId) use ($taskQueue, $table, $batchId) {

            echo "\nBatch $batchId Worker#{$workerId} started\n";

            // Process tasks from the queue
            while (true) {

                $currentTask = null;

                // Synchronize task fetching
                if (!$taskQueue->isEmpty()) {
                    $currentTask = $taskQueue->dequeue();
                } else {
                    echo "\nCompleted. All tasks have been removed from the queue\n";
                    break; // No more tasks
                }

                $taskIndex = $currentTask['index'];
                $task = $currentTask['task'];

                try {
                    //echo "\nWorker#{$workerId} executing task $taskIndex\n";
                    $result = $task(); // Execute the task
                    $table->set("task_$taskIndex", ['data' => json_encode($result)]);
                    echo "\nBatch $batchId Worker#{$workerId} completed task $taskIndex\n";
                } catch (\Throwable $e) {
                    $table->set("task_$taskIndex", ['data' => "Error: " . $e->getMessage()]);
                    echo "\nBatch $batchId Worker#{$workerId} error on task $taskIndex: {$e->getMessage()}\n";
                }
            }

            echo "\nBatch $batchId Worker#{$workerId} exiting\n";
            exit(0); // Explicitly signal worker exit
        });

        $pool->on("WorkerStop", function ($pool, int $workerId) use($batchId) {
            echo "\nBatch $batchId Worker#{$workerId} stopped\n";
        });

        // Start the pool
        $pool->start();

        // Collect results
        echo "\nCollecting results\n";
        $results = [];
        foreach ($tasks as $index => $task) {
            $data = $table->get("task_$index");
            if ($data) {
                $results[$index] = json_decode($data['data'], true);
            }
        }

        echo "\nReturning results\n";
        return $results;
    }
}
