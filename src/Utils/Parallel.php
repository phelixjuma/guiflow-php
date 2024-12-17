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

        // Shared memory table for inter-process communication
        $table = new Table(4096); // Increase rows if needed
        $table->column('data', Table::TYPE_STRING, 65536); // Larger string size for results
        $table->create();

        // Process pool
        $pool = new Pool($workerNum);

        // Worker logic
        $pool->on("WorkerStart", function (Pool $pool, int $workerId) use ($taskChunks, $table) {

            if (!isset($taskChunks[$workerId])) {
                return;
            }

            foreach ($taskChunks[$workerId] as $taskIndex => $task) {
                print "\nStarting to execute task $taskIndex in worker $workerId\n";
                try {

                    $result = $task();

                    print "\nStarting to save Task $taskIndex to table\n";

                    // Store result per task using unique keys
                    $table->set("{$workerId}_{$taskIndex}", ['data' => json_encode($result)]);

                    print "\nTask $taskIndex saved to table\n";

                } catch (\Throwable $e) {

                    print "\nError on Task $taskIndex: {$e->getMessage()}\n";

                    // Store error message
                    $table->set("{$workerId}_{$taskIndex}", ['data' => "Error: " . $e->getMessage()]);
                }
                print "\nCompleted task $taskIndex in worker $workerId\n";
            }
        });

        // Graceful signal handling
        \OpenSwoole\Process::signal(SIGTERM, function () use ($pool) {
            print "\nShutting down due to SIGTERM\n";
            $pool->shutdown();
        });

        $pool->start();

        // Collect results
        print "\nCollecting results\n";
        $results = [];
        foreach (range(0, $workerNum - 1) as $workerId) {
            foreach ($taskChunks[$workerId] as $taskIndex => $task) {
                $data = $table->get("{$workerId}_{$taskIndex}");
                if ($data) {
                    $results[] = json_decode($data['data'], true);
                }
            }
        }

        ksort($results); // Preserve original task order

        print "\nReturning results\n";

        return $results;
    }


}
