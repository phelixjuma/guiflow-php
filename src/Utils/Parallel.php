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

        // Dynamically determine worker count based on CPU cores
        $workerNum = $workerNum ?: Util::getCPUNum();

        print "\nnumber of workers: $workerNum\n";

        // Split tasks evenly among workers
        $taskChunks = array_chunk($tasks, ceil(count($tasks) / $workerNum));

        print_r($taskChunks);

        $results = [];

        // Shared memory table for inter-process communication
        $table = new Table(1024);
        $table->column('data', Table::TYPE_STRING, 8192);
        $table->create();

        // Process pool
        $pool = new Pool($workerNum);

        // Worker logic
        $pool->on("WorkerStart", function (Pool $pool, int $workerId) use ($taskChunks, $table) {
            if (!isset($taskChunks[$workerId])) {
                return;
            }

            $workerResults = [];
            foreach ($taskChunks[$workerId] as $taskIndex => $task) {
                print "\nStarting to execute task $workerId\n";
                try {
                    $workerResults[$taskIndex] = $task();
                } catch (\Throwable $e) {
                    $workerResults[$taskIndex] = "Error: " . $e->getMessage();
                }
                print "\ncompleted task $workerId: ".json_encode($workerResults)."\n";
            }

            // Safely store results in shared table
            $table->set((string)$workerId, ['data' => json_encode($workerResults)]);
        });

        // Graceful signal handling
        \OpenSwoole\Process::signal(SIGTERM, function () use ($pool) {
            print "\nShutting down due to SIGTERM\n";
            $pool->shutdown();
        });

        $pool->start();

        // Collect results
        print "\nCollecting results\n";
        foreach (range(0, $workerNum - 1) as $workerId) {
            $data = $table->get((string)$workerId);
            if ($data) {
                $results = array_merge($results, json_decode($data['data'], true));
            }
        }

        ksort($results); // Preserve original task order

        print "\nReturning results\n";

        return $results;
    }


}
