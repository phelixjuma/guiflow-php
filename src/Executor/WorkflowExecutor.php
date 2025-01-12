<?php

namespace PhelixJuma\GUIFlow\Executor;

use OpenSwoole\Core\Coroutine\WaitGroup;
use OpenSwoole\Coroutine as Co;
use OpenSwoole\Table;
use PhelixJuma\GUIFlow\Observability\ObservabilityService;
use Ramsey\Uuid\Uuid;

class WorkflowExecutor {

    private $dag;

    /**
     * @var Task[]
     */
    private array|null $results = [];
    private $taskResultsTable;
    private $startTime;
    private $endTime;

    public function __construct(DAG $dag) {
        $this->dag = $dag;
        $this->initializeResultsTable();
    }

    /**
     * @return void
     */
    private function initializeResultsTable() {

        // We create a table with 2 columns: one for holding task result and the other for holding data shared by all tasks
        $this->taskResultsTable = new Table(1024);
        $this->taskResultsTable->column('result', Table::TYPE_STRING, 20480000);
        $this->taskResultsTable->create();

    }

    /**
     * @param ObservabilityService $observabilityService
     * @param $workflowExecutionId
     * @return void
     */
    public function execute(ObservabilityService $observabilityService, $workflowExecutionId) {

        $this->startTime = microtime(true);

        co::run(function () use($observabilityService, $workflowExecutionId) {

            // Set status for workflow execution to running
            $observabilityService->updateWorkflowExecution($workflowExecutionId, [
                'execution_id'  => $workflowExecutionId,
                'status'        => TaskStatus::RUNNING
            ]);

            // A wait group to ensure we only return result after all tasks have completed
            $wg = new WaitGroup();

            // Get the sorted tasks
            $sortedTasks = $this->dag->topologicalSort();

            print_r($sortedTasks);

            foreach ($sortedTasks as $taskId) {

                // define workflow task execution id
                $workflowTaskExecutionId = Uuid::uuid4()->toString();

                print "\nexecution id for task {$taskId}: {$workflowTaskExecutionId}\n";

                $task = $this->dag->getTask($taskId);

                // Set as pending
                $task->setStatus(TaskStatus::PENDING);

                $observabilityService->logTaskExecution([
                    'id'                    => $task->getId(),
                    'execution_id'          => $workflowTaskExecutionId,
                    'parent_execution_id'   => $workflowExecutionId,
                    'status'                => TaskStatus::PENDING,
                    'start_time'            => microtime(true)
                ]);

                // This has been corrected to reflect that we're dealing with tasks
                // that may depend on the results of their parent tasks
                $parents = array_keys(array_filter($this->dag->parentToChildren, function($children) use ($taskId) {
                    return in_array($taskId, $children);
                }));

                // Add to wait group
                $wg->add();

                // Create a coroutine
                co::create(function () use ($task, $parents, $taskId, $wg, $observabilityService, $workflowTaskExecutionId) {

                    $parentResults = [];

                    foreach ($parents as $parentId) {

                        $parentResultSerialized = false;

                        // Loop to wait for the parent task to complete and get its result
                        while ($parentResultSerialized === false) {

                            $parentResultSerialized = $this->taskResultsTable->get($parentId, 'result');

                            if ($parentResultSerialized !== false) {
                                $parentResults[$parentId] = unserialize($parentResultSerialized);
                            } else {
                                // Implement a short delay to prevent a busy wait loop
                                co::sleep(0.001); // Sleep for 1 millisecond
                            }
                        }

                    }

                    // Execute the task, potentially using results from parent tasks
                    $result = null;
                    try {

                        // Mark as started
                        $task->setStatus(TaskStatus::RUNNING);

                        $observabilityService->updateTaskExecution($workflowTaskExecutionId, [
                            'status'        => TaskStatus::RUNNING,
                            'inputs'        => $parentResults,
                        ]);

                        // Execute the task
                        $result = call_user_func($task->getCallable(), $parentResults);

                        // Mark as completed (successfully)
                        $task->setStatus(TaskStatus::COMPLETED);

                        $observabilityService->updateTaskExecution($workflowTaskExecutionId, [
                            'status'        => TaskStatus::COMPLETED,
                            'end_time'      => microtime(true),
                            'output'        => $result,
                        ]);

                    } catch (\Throwable|\Exception $e) {

                        // Failed. Set the exception
                        $task->setException($e->getMessage());
                        $task->setExceptionTrace($e->getTrace());

                        // Mark status as failed
                        $task->setStatus(TaskStatus::FAILED);

                        $observabilityService->updateTaskExecution($workflowTaskExecutionId, [
                            'status'        => TaskStatus::FAILED,
                            'end_time'      => microtime(true),
                            'error_message' => $e->getMessage(),
                            'error_trace'   => $e->getTraceAsString(),
                        ]);

                    }

                    // Set the task result
                    $task->setResult($result);

                    // Store result in the Swoole Table
                    $this->taskResultsTable->set($taskId, ['result' => serialize($result)]);

                    $this->results[] = $task;

                    // At the end of the task execution, we update wait group
                    $wg->done();

                });
            }

            // Wait for all tasks to complete
            $wg->wait();

            // We clean the Swoole table
            $this->cleanAndGetResults();

            $this->endTime = microtime(true);

            // Completed workflow. We update status to completed
            $observabilityService->updateWorkflowExecution($workflowExecutionId, [
                'status'        => TaskStatus::COMPLETED,
                'end_time'      => $this->endTime,
                'output_state'  => $this->getFinalResult($sortedTasks[sizeof($sortedTasks)-1])
            ]);
        });
    }

    /**
     * @return Task[]
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @param $lastTaskId
     * @return mixed|Task|null
     */
    public function getFinalResult($lastTaskId) {

        print "\nlast key: $lastTaskId\n";

        return $this->results[$lastTaskId] ?? null;
    }

    private function cleanAndGetResults() {

        // Iterate over the Swoole Table to collect results
        foreach ($this->taskResultsTable as $taskId => $row) {
            // Unserialize the result before adding it to the results array
            $result = unserialize($row['result']);
            $this->results[$taskId] = $result;
        }

        // Clean the table if no longer needed
        unset($this->taskResultsTable);
    }

    /**
     * @return mixed
     */
    public function getExecutionTime() {
        return $this->endTime - $this->startTime;
    }

}
