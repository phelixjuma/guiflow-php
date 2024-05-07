<?php

namespace PhelixJuma\GUIFlow;

use JumaPhelix\DAG\DAG;
use JumaPhelix\DAG\SharedDataManager;
use JumaPhelix\DAG\Task;
use JumaPhelix\DAG\TaskExecutor;
use PhelixJuma\GUIFlow\Actions\AddAction;
use PhelixJuma\GUIFlow\Actions\DeleteValueAction;
use PhelixJuma\GUIFlow\Actions\DivideAction;
use PhelixJuma\GUIFlow\Actions\FunctionAction;
use PhelixJuma\GUIFlow\Actions\MultiplyAction;
use PhelixJuma\GUIFlow\Actions\RemovePathAction;
use PhelixJuma\GUIFlow\Actions\SetValueAction;
use PhelixJuma\GUIFlow\Actions\SubtractAction;
use PhelixJuma\GUIFlow\Conditions\CompositeCondition;
use PhelixJuma\GUIFlow\Conditions\SimpleCondition;
use PhelixJuma\GUIFlow\Utils\ConfigurationValidator;
use PhelixJuma\GUIFlow\Utils\PathResolver;

class Workflow
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
        ConfigurationValidator::validate($this->config, 'v3');
    }

    /**
     * @param $data
     * @param bool $parallelize
     * @return void
     */
    public function run(&$data, bool $parallelize = false): void
    {

        $dataCopy = $data;
        $data = [];

        if (is_array($dataCopy)) {
            if (self::isObject($dataCopy)) {

                // For an object, we transform it
                $this->runWorkFlowOnObject($dataCopy, $parallelize);

                // Set the response into data: checking if the response has been split or not.
                if (self::isObject($dataCopy)) {
                    $data[] = $dataCopy;
                } else {
                    $data = $dataCopy;
                }

            } else {
                // it's an array, we loop
                foreach ($dataCopy as $item) {

                    $this->runWorkFlowOnObject($item, $parallelize);

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
    private function runWorkFlowOnObject(&$data, $isParallel = false) {
        if ($isParallel) {
            $this->runWorkFlowOnObjectParallel($data);
        } else {
            $this->runWorkFlowOnObjectSerial($data);
        }
    }

    /**
     * @param $data
     * @return void
     */
    private function runWorkFlowOnObjectSerial(&$data): void
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
                                $error = [
                                    'action'    => $action['stage'],
                                    'message' => $e->getMessage(),
                                    'trace' => $e->getTrace()
                                ];
                                $this->errors[] = $error;
                            }
                        }
                    }
                } catch (\Exception|\Throwable $e ) {
                    $error = [
                        'rule'  => $rule['stage'],
                        'message' => $e->getMessage(),
                        'trace' => $e->getTrace()
                    ];
                    $this->errors[] = $error;
                }
            }

        } catch (\Exception|\Throwable $e ) {
            $error = [
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
            ];
            $this->errors[] = $error;
        }
    }

    /**
     * @param $inputData
     * @return void
     */
    private function runWorkFlowOnObjectParallel(&$inputData): void
    {

        try {

            $config = json_decode(json_encode($this->config), JSON_FORCE_OBJECT);

            $this->workflowDAG = new DAG();
            $dataManager = new SharedDataManager($inputData);

            foreach ($config as $index => $rule) {

                $skip = $rule['skip'] ?? 0;
                $ruleStage = "rule_" . (!empty($rule['stage']) ? $rule['stage'] : $index);
                $ruleDependencies = $rule['dependencies'] ?? [];
                $condition = $rule['condition'];
                $actions = $rule['actions'];

                // Define the rule task, which is to evaluate its condition.
                $ruleTask = new Task($ruleStage, function() use ($ruleStage, $dataManager, $skip, $condition) {

                    // Evaluate the rule's condition to determine if actions should be skipped
                    $isSkipped = $skip == 1 || !self::evaluateCondition($dataManager->getData(), $condition);

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

                        $shouldSkipRule = array_reduce($parentResults, function($carry, $result) {
                            return $carry || (isset($result['isSkipped']) && $result['isSkipped']);
                        }, false);

                        // We must pass data by reference since all transformers must work on the same data
                        $dataToUse = &$dataManager->getData();

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
                            $dataManager->modifyData(function($currentData) use(&$dataToUse) {
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

            // Initialize the task executor
            $this->workflowExecutor = new TaskExecutor($this->workflowDAG);
            $this->workflowExecutor->execute();

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

        // get the action class
        $actionClass = self::getActionClass($action);
        // get the action parameters
        $actionParams = $action['params'];

        switch ($action['action']) {
            case 'add':
            case 'subtract':
            case 'multiply':
            case 'divide':
                $actionInstance = new $actionClass($actionParams['path'], $actionParams['value'] ?? null, $actionParams['valueFromField'] ?? null, $actionParams['newField'] ?? null);
                break;
            case 'set':
                $actionInstance = new $actionClass($actionParams['path'], $actionParams['value'] ?? null, $actionParams['valueFromField'] ?? null, $actionParams['valueMapping'] ?? null, $actionParams['conditionalValue'] ?? null, $actionParams['newField'] ?? null);
                break;
            case 'remove_path':
            case 'delete':
                $actionInstance = new $actionClass($actionParams['path']);
                break;
            case 'function':
                $actionInstance = new $actionClass($actionParams['path'], [$this->functionsClass, $actionParams['function']], $actionParams['args'] ?? [], $actionParams['newField'] ?? null, $actionParams['strict'] ?? null, $actionParams['condition'] ?? null);
                break;
            default:
                throw new \InvalidArgumentException('Unknown action type: ' . $actionParams['action']);
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
