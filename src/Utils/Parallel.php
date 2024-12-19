<?php

namespace PhelixJuma\GUIFlow\Utils;

use parallel\Runtime;
use RuntimeException;

class Parallel {

    public static function parallelBatch(array $tasks, $batchId = null): array {

        if (!extension_loaded('parallel')) {
            throw new RuntimeException('The parallel extension is not available.');
        }

        $batchId = $batchId ?: Randomiser::getRandomString(5);

        // Get the number of CPU cores
        $numCores = shell_exec('nproc'); // or use PHP's `sys_getloadavg()` for a more portable solution
        $numCores = (int)$numCores;

        $totalTasks = count($tasks);
        $results = [];
        $futures = [];
        $runtime = new Runtime();
        $taskIndex = 0; // Track the next task to submit

        echo "\n[Batch-{$batchId}] Starting batch processing with {$totalTasks} tasks using {$numCores} workers\n";

        // Submit initial tasks up to the number of cores
        for ($i = 0; $i < min($numCores, $totalTasks); $i++) {
            echo "[Batch-{$batchId}] Worker-{$taskIndex} submitted\n";
            $futures[$taskIndex] = $runtime->run($tasks[$taskIndex++]); // Submit the task and increment the index
        }

        // Process tasks as they complete
        while ($taskIndex < $totalTasks || !empty($futures)) {
            // Check for completed futures
            foreach ($futures as $key => $future) {

                if ($future->done()) { // Check if the future is done
                    echo "[Batch-{$batchId}] Worker-{$key} completed\n";
                    // Collect the result of the completed future
                    try {
                        $results[] = $future->value();
                        echo "[Batch-{$batchId}] Worker-{$key} completed with response as ".json_encode($future->value())." \n";
                    } catch (\Throwable $e) {
                        // Handle exceptions from the task
                        $results[] = 'Error: ' . $e->getMessage();
                        echo "[Batch-{$batchId}] Worker-{$key} completed with error as {$e->getMessage()} \n";
                    }

                    // Submit the next task if available
                    if ($taskIndex < $totalTasks) {
                        echo "[Batch-{$batchId}] Worker-{$taskIndex} submitted\n";
                        $futures[$key] = $runtime->run($tasks[$taskIndex++]); // Submit the next task
                    } else {
                        unset($futures[$key]); // Remove the future if no more tasks are available
                    }
                    break; // Exit the loop to avoid modifying the array while iterating
                }
            }
        }

        // clean
        unset($runtime);

        return $results;
    }

    /**
     * Parallel batch processing
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

        $batchId = $batchId ?: Randomiser::getRandomString(5);
        $workerNum = min($workerNum ?: (int)shell_exec('nproc'), count($tasks));
        $totalTasks = count($tasks);

        echo "\n[Batch-{$batchId}] Starting batch processing with {$totalTasks} tasks using {$workerNum} workers\n";

        // Create a channel for communication
        $channel = new Channel();

        // Start workers
        $runtimes = [];
        for ($i = 0; $i < $workerNum; $i++) {
            $runtime = new Runtime();
            $runtimes[] = $runtime;

            $runtime->run(function (Channel $channel, int $workerId) {
                echo "[Batch] Worker-{$workerId} started\n";

                while (true) {
                    $task = $channel->recv();

                    // Check if the task is null (stop signal)
                    if ($task === null || !isset($task['index']) || !isset($task['function'])) {
                        break;
                    }

                    // Process the task
                    if (!empty($task['index']) && !empty($task['function'])) {
                        $taskIndex = $task['index'];
                        $taskFunction = $task['function'];

                        try {
                            $result = $taskFunction();
                            $channel->send(['index' => $taskIndex, 'result' => $result, 'error' => null]);
                        } catch (\Throwable $e) {
                            $channel->send(['index' => $taskIndex, 'result' => null, 'error' => $e->getMessage()]);
                        }
                    } else {
                        break;
                    }
                }

                echo "[Batch] Worker-{$workerId} finished\n";
            }, [$channel, $i]);
        }

        // Distribute tasks
        foreach ($tasks as $index => $taskFunction) {
            $channel->send(['index' => $index, 'function' => $taskFunction]);
        }

        // Send null to signal workers to stop
        for ($i = 0; $i < $workerNum; $i++) {
            $channel->send(null);
        }

        // Collect results
        $results = array_fill(0, $totalTasks, null);
        for ($i = 0; $i < $totalTasks; $i++) {
            $response = $channel->recv();
            $index = $response['index'];
            if ($response['error']) {
                $results[$index] = ['error' => $response['error']];
            } else {
                $results[$index] = $response['result'];
            }
        }

        echo "[Batch-{$batchId}] Batch processing completed.\n";

        return $results;
    }

}
