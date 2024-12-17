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

        // Dynamically determine the number of workers
        $workerNum = min($workerNum ?: Util::getCPUNum(), count($tasks));

        print "\nNumber of workers: $workerNum\n";

        // Shared memory table for inter-process communication
        $table = new Table(count($tasks));
        $table->column('data', Table::TYPE_STRING, 65536);
        $table->create();

        // Shared task queue
        $taskQueue = new \SplQueue();
        foreach ($tasks as $index => $task) {
            $taskQueue->enqueue(['index' => $index, 'task' => $task]);
        }
        $taskQueue->setIteratorMode(\SplQueue::IT_MODE_DELETE);

        // Process pool
        $pool = new Pool($workerNum);

        $pool->on("WorkerStart", function (Pool $pool, int $workerId) use ($taskQueue, $table) {
            echo "\nWorker#{$workerId} is started\n";

            while (true) {
                // Fetch a task from the queue
                $currentTask = null;

                // Synchronize access to the queue to avoid race conditions
                if (!$taskQueue->isEmpty()) {
                    $currentTask = $taskQueue->dequeue();
                } else {
                    break; // Exit if no tasks remain
                }

                $taskIndex = $currentTask['index'];
                $task = $currentTask['task'];

                try {
                    echo "\nWorker#{$workerId} executing task $taskIndex\n";
                    $result = $task();
                    $table->set("task_$taskIndex", ['data' => json_encode($result)]);
                    echo "\nWorker#{$workerId} completed task $taskIndex\n";
                } catch (\Throwable $e) {
                    $table->set("task_$taskIndex", ['data' => "Error: " . $e->getMessage()]);
                    echo "\nWorker#{$workerId} error on task $taskIndex: {$e->getMessage()}\n";
                }
            }

            echo "\nWorker#{$workerId} exiting\n";
        });

        $pool->on("WorkerStop", function (Pool $pool, int $workerId) {
            echo "\nWorker#{$workerId} is stopped\n";
        });

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
