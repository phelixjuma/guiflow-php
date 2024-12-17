<?php

namespace PhelixJuma\GUIFlow\Utils;

//use OpenSwoole\Process\Pool;
//use OpenSwoole\Table;
//use OpenSwoole\Util;

use Spatie\Async\Pool;

class Parallel {

    /**
     * Parallel batch processing using OpenSwoole's Process Pool
     *
     * @param array $tasks
     * @param int|null $workerNum
     * @param string|null $batchId
     * @return array
     */
    public static function parallelBatch_(array $tasks, int $workerNum = null, $batchId = null): array
    {
        if (empty($tasks)) {
            return [];
        }

        // Dynamically determine the number of workers
        $workerNum = min($workerNum ?: Util::getCPUNum(), count($tasks));

        echo "\n$batchId Number of workers: $workerNum\n";

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

        $pool->on("WorkerStart", function (Pool $pool, int $workerId) use ($taskQueue, $table, $batchId) {
            echo "\n$batchId Worker#{$workerId} is started\n";

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
                    echo "\n$batchId Worker#{$workerId} executing task $taskIndex\n";
                    $result = $task();
                    $table->set("task_$taskIndex", ['data' => json_encode($result)]);
                    echo "\n$batchId Worker#{$workerId} completed task $taskIndex\n";
                } catch (\Throwable $e) {
                    $table->set("task_$taskIndex", ['data' => "Error: " . $e->getMessage()]);
                    echo "\n$batchId Worker#{$workerId} error on task $taskIndex: {$e->getMessage()}\n";
                }
            }

            echo "\n$batchId Worker#{$workerId} exiting\n";

            // Explicitly terminate the worker process
            exit(0);
        });

        $pool->on("WorkerStop", function (Pool $pool, int $workerId) use($batchId) {
            echo "\n$batchId Worker#{$workerId} is stopped\n";
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

    /**
     * @param array $tasks
     * @return array
     */
    public static function parallelBatch(array $tasks, $batchId=null): array
    {
        if (empty($tasks)) {
            return [];
        }

        // Determine the number of parallel processes
        $workerNum = min(Utils::count_vcpus(), count($tasks));

        $pool = Pool::create()->concurrency($workerNum);

        echo "\n$batchId batch: Starting pool of $workerNum\n";

        $results = [];
        $errors = [];

        foreach ($tasks as $index => $task) {
            $pool->add(function () use ($task) {
                // Execute the task and return the result
                return $task();
            })->then(function ($output) use (&$results, $index) {
                // On success, store the result
                $results[$index] = $output;
            })->catch(function ($exception) use (&$errors, $index) {
                // On failure, store the error
                $errors[$index] = $exception->getMessage();
            });
        }

        // Wait for all tasks to finish
        $pool->wait();

        echo "\n$batchId batch: Completed all pool tasks\n";

        // Merge results and errors for unified output
        foreach ($errors as $index => $error) {
            $results[$index] = "Error: " . $error;
        }

        ksort($results); // Preserve task order
        return $results;
    }

}
