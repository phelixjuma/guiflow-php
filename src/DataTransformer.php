<?php

namespace PhelixJuma\DataTransformer;

use JumaPhelix\DAG\DAG;
use JumaPhelix\DAG\SharedDataManager;
use JumaPhelix\DAG\Task;
use JumaPhelix\DAG\TaskExecutor;
use PhelixJuma\DataTransformer\Actions\AddAction;
use PhelixJuma\DataTransformer\Actions\DeleteValueAction;
use PhelixJuma\DataTransformer\Actions\DivideAction;
use PhelixJuma\DataTransformer\Actions\FunctionAction;
use PhelixJuma\DataTransformer\Actions\MultiplyAction;
use PhelixJuma\DataTransformer\Actions\RemovePathAction;
use PhelixJuma\DataTransformer\Actions\SetValueAction;
use PhelixJuma\DataTransformer\Actions\SubtractAction;
use PhelixJuma\DataTransformer\Conditions\CompositeCondition;
use PhelixJuma\DataTransformer\Conditions\SimpleCondition;
use PhelixJuma\DataTransformer\Utils\ConfigurationValidator;
use PhelixJuma\DataTransformer\Utils\PathResolver;

class DataTransformer
{
    private array $config;
    private object $functionsClass;
    //private PathResolver $pathResolver;

    private $taskResults = [];
    public $errors = [];

    /**
     * @var DAG
     */
    public DAG $workflowDAG;

    /**
     * @var TaskExecutor
     */
    public TaskExecutor $workflowExecutor;


    public function __construct(array $config, object $functionsClass)
    {
        $this->config = $config;
        $this->functionsClass = $functionsClass;

        // Validate the configuration against the schema
        ConfigurationValidator::validate($this->config);
    }

    /**
     * @param $data
     * @return void
     */
    public function transform(&$data, $parallelize = false) {

        $dataCopy = $data;
        $data = [];

        if (is_array($dataCopy)) {
            if (self::isObject($dataCopy)) {

                // For an object, we transform it
                $this->transformObject($dataCopy, $parallelize);

                // Set the response into data: checking if the response has been split or not.
                if (self::isObject($dataCopy)) {
                    $data[] = $dataCopy;
                } else {
                    $data = $dataCopy;
                }

            } else {
                // it's an array, we loop
                foreach ($dataCopy as $item) {

                    $this->transformObject($item, $parallelize);

                    // Set the response into data: checking if the response has been split or not.
                    if (self::isObject($item)) {
                        $data[] = $item;
                    } else {
                        // For a split response, we flatten by adding each item to data
                        foreach ($item as $it) {
                            $data[] = $it;
                        }
                    }
                }
            }
        }

    }

    /**
     * @param $data
     * @param $isParallel
     * @return void
     */
    private function transformObject(&$data, $isParallel = false) {
        if ($isParallel) {
            $this->transformObjectParallel($data);
        } else {
            $this->transformObjectSerial($data);
        }
    }

    /**
     * @param $data
     * @return void
     */
    private function transformObjectSerial(&$data): void
    {

        try {

            $config = json_decode(json_encode($this->config), JSON_FORCE_OBJECT);

            foreach ($config as $rule) {

                try {

                    $skip = $rule['skip'] ?? 0;
                    $condition = $rule['condition'];
                    $actions = $rule['actions'];

                    // We execute this rule if it is not skipped and the conditions are true
                    if ($skip != 1 && self::evaluateCondition($data, $condition)) {
                        // Execute the actions
                        foreach ($actions as $action) {
                            try {

                                $skipAction = $action['skip'] ?? 0;

                                // We execute the action, if it is not set to be skipped.
                                if ($skipAction != 1) {

                                    if (self::isObject($data)) {
                                        $this->executeAction($data, $action);
                                    } else {
                                        array_walk($data, function (&$value, $key) use($action) {
                                            $this->executeAction($value, $action);
                                        });
                                    }
                                }

                            } catch (\Exception|\Throwable $e ) {
                                $this->errors[] = $e->getMessage();
                            }
                        }
                    }
                } catch (\Exception|\Throwable $e ) {
                    $this->errors[] = $e->getMessage();
                }
            }

        } catch (\Exception|\Throwable $e ) {
            $this->errors[] = $e->getMessage();
        }
    }

    private function transformObjectParallel(&$inputData): void
    {

        try {

            $config = json_decode(json_encode($this->config), JSON_FORCE_OBJECT);

            $this->workflowDAG = new DAG();
            $dataManager = new SharedDataManager($inputData);

            //print "\nStarting data:\n";
            //print_r($inputData);

            foreach ($config as $index => $rule) {

                $skip = $rule['skip'] ?? 0;
                $ruleStage = "rule_" . (!empty($rule['stage']) ? $rule['stage'] : $index);
                $ruleDependencies = $rule['dependencies'] ?? [];
                $condition = $rule['condition'];
                $actions = $rule['actions'];

                // Define the rule task, which is to evaluate its condition.
                $ruleTask = new Task($ruleStage, function() use ($ruleStage, $dataManager, $skip, $condition) {

                    // Evaluate the rule's condition to determine if actions should be skipped
                    //print "\nRunning rule task $ruleStage\n";
                    $isSkipped = $skip == 1 || !self::evaluateCondition($dataManager->getData(), $condition);

                    //print "\nCompleted rule $ruleStage; skip is $skip. Response is ".json_encode($isSkipped). "\n";

                    return ['isSkipped' => $isSkipped];
                });

                // Add the rule task to the workflow.
                $this->workflowDAG->addTask($ruleTask);

                // Add the actions
                foreach ($actions as $actionIndex => $action) {

                    $actionStage = "action_" . ( !empty($action['stage']) ? $action['stage'] : $actionIndex);
                    $actionDependencies = $action['dependencies'] ?? [];
                    $skipAction = $action['skip'] ?? 0;

                    $actionTask = new Task($actionStage, function($parentResults) use ($dataManager,$actionStage, $action, $skipAction) {

                        //print "\nRunning action task $actionStage\n";

                        $shouldSkipRule = array_reduce($parentResults, function($carry, $result) {
                            return $carry || (isset($result['isSkipped']) && $result['isSkipped']);
                        }, false);

                        //print "\nRule skipping is ".json_encode($shouldSkipRule).". Action skipping is ".json_encode($skipAction). "\n";

                        $dataToUse = $dataManager->getData();

                        if (!$shouldSkipRule && !$skipAction) {

                            // Execute the action logic here if the rule was not skipped
                            if (self::isObject($dataToUse)) {
                                $this->executeAction($dataToUse, $action);
                            } else {
                                array_walk($dataToUse, function (&$value, $key) use($action) {
                                    $this->executeAction($value, $action);
                                });
                            }

                            // We modify the data manager data
                            $dataManager->modifyData(function($data) use($dataToUse) {
                                return $dataToUse;
                            });

                        }
                        return $dataToUse;
                    });

                    // Add the action to the workflow
                    $this->workflowDAG->addTask($actionTask);
                    // We add the rule as a dependency to this action.
                    $this->workflowDAG->addParent($actionStage, $ruleStage);

                    // Add Action Task dependencies
                    if (!empty($actionDependencies)) {
                        foreach ($actionDependencies as $actionDependency) {
                            $this->workflowDAG->addParent($actionStage, "action_$actionDependency");
                        }
                    }
                }

                // Add Rule Task dependencies
                if (!empty($ruleDependencies)) {
                    foreach ($ruleDependencies as $dependency) {
                        $this->workflowDAG->addParent($ruleStage, "action_$dependency");
                    }
                }
            }

            //print "\nTasks:\n";
            //print_r($this->workflowDAG->visualize());

            // Initialize the task executor
            $this->workflowExecutor = new TaskExecutor($this->workflowDAG);
            $this->workflowExecutor->execute();

            //$executionTime = $executor->getExecutionTime();
            //print "\nAll tasks executed in  $executionTime seconds\n";

            //$taskResults = $executor->getTaskResults();
            //$allResults = $executor->getResults();
            //$lastResult = $executor->getFinalResult();

            //print "\nAll Results\n";
            //print_r($allResults[0]->getStatus());

            //print "\nFinal Result\n";
            //print_r($lastResult->getExecutionTime());

            //print "\nShared Data\n";
            //print_r($dataManager->getData());

            //print "\nAll tasks completed in {$executor->getExecutionTime()} seconds \n";

        } catch (\Exception|\Throwable $e ) {
            $this->errors[] = $e->getMessage();
        }
    }

    private static function isObject($data) {
        return  array_keys($data) !== range(0, count($data) - 1);
    }

    /**
     * @param $condition
     * @param $pathValue
     * @return void
     */
    private static function addPathValueToCondition(&$condition, $pathValue) {

        if (!is_array($condition)) {
            return;
        }

        if (isset($condition['operator'])) {
            // Add 'path_value' field. This can be set to any value as needed.
            $condition['path_value'] = $pathValue;  // Set this to your desired value
        }

        if (isset($condition['conditions']) && is_array($condition['conditions'])) {
            foreach ($condition['conditions'] as &$subCondition) {
                self::addPathValueToCondition($subCondition, $pathValue);
            }
        }
    }

    /**
     * @param $data
     * @param $condition
     * @param $useDataAsPathValue
     * @return mixed
     */
    public static function evaluateCondition($data, $condition, $useDataAsPathValue = false)
    {
        // We add path value, if set
        if ($useDataAsPathValue) {
            self::addPathValueToCondition($condition, $data);
        }

        $conditionClass = self::getConditionClass($condition);
        $conditionInstance = new $conditionClass($condition, (new PathResolver()));
        return $conditionInstance->evaluate($data);
    }


    /**
     * @param $data
     * @param $action
     * @return void
     */
    private function executeAction(&$data, $action)
    {
        $actionClass = self::getActionClass($action);

        switch ($action['action']) {
            case 'add':
            case 'subtract':
            case 'multiply':
            case 'divide':
                $actionInstance = new $actionClass($action['path'], $action['value'] ?? null, $action['valueFromField'] ?? null, $action['newField'] ?? null);
                break;
            case 'set':
                $actionInstance = new $actionClass($action['path'], $action['value'] ?? null, $action['valueFromField'] ?? null, $action['valueMapping'] ?? null, $action['conditionalValue'] ?? null, $action['newField'] ?? null);
                break;
            case 'remove_path':
            case 'delete':
                $actionInstance = new $actionClass($action['path']);
                break;
            case 'function':
                $actionInstance = new $actionClass($action['path'], [$this->functionsClass, $action['function']], $action['args'] ?? [], $action['newField'] ?? null, $action['strict'] ?? null, $action['condition'] ?? null);
                break;
            default:
                throw new \InvalidArgumentException('Unknown action type: ' . $action['action']);
        }
        $actionInstance->execute($data);
    }


    private static function getConditionClass($condition): string
    {
        if (isset($condition['operator']) && in_array(strtolower($condition['operator']), ['and', 'or'])) {
            return CompositeCondition::class;
        } else {
            return SimpleCondition::class;
        }
    }

    private static function getActionClass($action): string
    {
        switch ($action['action']) {
            case 'add':
                return AddAction::class;
            case 'subtract':
                return SubtractAction::class;
            case 'multiply':
                return MultiplyAction::class;
            case 'divide':
                return DivideAction::class;
            case 'set':
                return SetValueAction::class;
            case 'delete':
                return DeleteValueAction::class;
            case 'remove_path':
                return RemovePathAction::class;
            case 'function':
                return FunctionAction::class;
            default:
                throw new \InvalidArgumentException('Unknown action type: ' . $action['action']);
        }
    }
}
