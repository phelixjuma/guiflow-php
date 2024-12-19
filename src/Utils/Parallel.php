<?php

namespace PhelixJuma\GUIFlow\Utils;

use parallel\Runtime;
use parallel\Channel;

class Parallel {

    /**
     * Parallel batch processing using OpenSwoole's Process
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

                while (($task = $channel->recv()) !== null) {
                    $taskIndex = $task['index'];
                    $taskFunction = $task['function'];
                    try {
                        $result = $taskFunction();
                        $channel->send(['index' => $taskIndex, 'result' => $result, 'error' => null]);
                    } catch (\Throwable $e) {
                        $channel->send(['index' => $taskIndex, 'result' => null, 'error' => $e->getMessage()]);
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
