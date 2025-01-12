<?php

namespace PhelixJuma\GUIFlow;

use Exception;
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
use PhelixJuma\GUIFlow\Executor\DAG;
use PhelixJuma\GUIFlow\Executor\SharedDataManager;
use PhelixJuma\GUIFlow\Executor\Task;
use PhelixJuma\GUIFlow\Executor\TaskStatus;
use PhelixJuma\GUIFlow\Executor\WorkflowExecutor;
use PhelixJuma\GUIFlow\Observability\ObservabilityService;
use PhelixJuma\GUIFlow\Utils\ConfigurationValidator;
use PhelixJuma\GUIFlow\Utils\PathResolver;
use PhelixJuma\GUIFlow\Utils\Utils;

use OpenSwoole\Coroutine as Co;
use Ramsey\Uuid\Uuid;
use ReflectionException;
use function OpenSwoole\Core\Coroutine\batch;

class Workflow
{

    private $id;
    private $parent_id;

    private array $config;
    private object $functionsClass;

    public $errors = [];

    /**
     * @var DAG
     */
    public DAG $workflowDAG;

    /**
     * @var WorkflowExecutor
     */
    public WorkflowExecutor $workflowExecutor;

    protected ObservabilityService $observabilityService;

    /**
     * @param $id
     * @param array $config
     * @param object $functionsClass
     * @param $observabilityBackend
     * @param $observabilityConfigs
     * @throws ReflectionException|Exception
     */
    public function __construct($id, array $config, object $functionsClass, $observabilityBackend = '', $observabilityConfigs = [])
    {
        $this->id = $id;
        $this->config = $config;
        $this->functionsClass = $functionsClass;

        // Validate the configuration against the schema
        ConfigurationValidator::validate($this->config, 'v3');

        $this->observabilityService = new ObservabilityService($observabilityConfigs, $observabilityBackend);
    }

    public function getId() {
        return $this->id;
    }

    public function setParentId($parentId) {
        return $this->parent_id = $parentId;
    }

    public function getParentId() {
        return $this->parent_id;
    }

    /**
     * @param $executionId
     * @return mixed
     * @throws Exception
     */
    public function getExecutionById($executionId): mixed
    {
        return $this->observabilityService->getExecutionById($executionId);
    }

    /**
     * @param $data
     * @param bool $parallelize
     * @return string
     */
    public function run(&$data, bool $parallelize = false): string
    {
        if (Utils::isList($data)) {
            foreach ($data as $index => &$d) {
                $d['workflow_list_position'] = $index;
            }
        }

        if ($parallelize) {
            return $this->runWorkFlowParallel($data);
        } else {
            return $this->runWorkFlowSerial($data);
        }
    }

    /**
     * @param $inputData
     * @return string
     */
    private function runWorkFlowSerial(&$inputData): string
    {

        $workflowExecutionId = Uuid::uuid4()->toString();

        // We execute within a coroutine environment to support parallelization
        co::run(function() use(&$inputData) {
            try {

                $config = json_decode(json_encode($this->config), JSON_FORCE_OBJECT);

                foreach ($config as $rule) {

                    try {

                        if (Utils::isObject($inputData)) {
                            $this->executeRuleSerial($rule, $inputData);
                        } else {

                            $tempData = [];

                            $tasks = [];
                            foreach ($inputData as $data) {

                                $tasks[] = function () use($data, $rule) {

                                    $this->executeRuleSerial($rule, $data);

                                    // small delay to yield to the event loop
                                    co::sleep(1);

                                    if (Utils::isObject($data)) {
                                        // An object. Set to temp data
                                        return [$data];
                                    } else {
                                        return $data;
                                    }
                                };
                            }
                            // We fetch the results from all the tasks
                            $results = batch($tasks);

                            // Flatten the results and merge them into $tempData
                            foreach ($results as $result) {
                                if (is_array($result)) {
                                    $tempData = array_merge($tempData, $result);
                                } else {
                                    $tempData[] = $result;
                                }
                            }

                            // Set the temp data to input data
                            $inputData = $tempData;
                        }
                    } catch (Exception|\Throwable $e ) {
                        $error = [
                            'rule'  => $rule['stage'],
                            'message' => $e->getMessage(),
                            'trace' => $e->getTrace()
                        ];
                        $this->errors[] = $error;
                    }
                }

            } catch (Exception|\Throwable $e ) {
                $error = [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTrace()
                ];
                $this->errors[] = $error;
            }
        });

        return $workflowExecutionId;
    }

    /**
     * @param $inputData
     * @return string
     */
    private function runWorkFlowParallel(&$inputData): string
    {

        $workflowExecutionId = Uuid::uuid4()->toString();

        $this->observabilityService->logWorkflowExecution([
            'id'            => $this->id,
            'parent_id'     => $this->parent_id,
            'execution_id'  => $workflowExecutionId,
            'status'        => TaskStatus::PENDING,
            'input_state'   => $inputData,
            'start_time'    => microtime(true)
        ]);

        try {

            $config = json_decode(json_encode($this->config), JSON_FORCE_OBJECT);

            $this->workflowDAG = new DAG();

            foreach ($config as $ruleIndex => $rule) {

                if (Utils::isObject($inputData)) {

                    $dataManager = new SharedDataManager($inputData);
                    $this->addRulesToDAG($rule, $ruleIndex, $dataManager);

                } else {
                    $tempData = [];
                    foreach ($inputData as $dataIndex => &$data) {

                        $data['workflow_list_position'] = $dataIndex;

                        $dataManager = new SharedDataManager($data);
                        $this->addRulesToDAG($rule, $ruleIndex, $dataManager);

                        if (Utils::isObject($data)) {
                            $tempData[] = $data;
                        } else {
                            // For a split response, we flatten by adding each item to data
                            foreach ($data as $d) {
                                $tempData[] = $d;
                            }
                        }
                    }
                    $inputData = $tempData;
                }
            }

            // Initialize the task executor
            $this->workflowExecutor = new WorkflowExecutor($this->workflowDAG);
            $this->workflowExecutor->execute($this->observabilityService, $workflowExecutionId);

        } catch (Exception|\Throwable $e ) {

            $this->errors[] = $e->getMessage();

            $this->observabilityService->updateWorkflowExecution($workflowExecutionId, [
                'status'        => TaskStatus::FAILED,
                'end_time'      => microtime(true),
                'error_message' => $e->getMessage(),
                'error_trace'   => $e->getTraceAsString()
            ]);

        }

        return $workflowExecutionId;
    }

    /**
     * @param $rule
     * @param $data
     * @return void
     */
    private function executeRuleSerial($rule, &$data): void
    {

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

                        if (Utils::isObject($data)) {
                            $this->executeAction($data, $action);
                        } else {


                            // we parallelize the execution of each action for the dataset
                            $temp = [];
                            $tasks = [];
                            foreach ($data as $datum) {

                                $tasks[] = function () use ($datum, $action) {

                                    $this->executeAction($datum, $action);

                                    // small delay to yield to the event loop
                                    co::sleep(1);

                                    if (Utils::isObject($datum)) {
                                        return [$datum];
                                    } else {
                                        return $datum;
                                    }
                                };
                            }

                            // We fetch the results from all the tasks
                            $results = batch($tasks);

                            // Flatten the results and merge them into $tempData
                            foreach ($results as $result) {
                                if (is_array($result)) {
                                    $temp = array_merge($temp, $result);
                                } else {
                                    $temp[] = $result;
                                }
                            }

                            // Set the temp data to input data
                            $data = $temp;
                        }
                    }

                } catch (Exception|\Throwable $e ) {
                    $error = [
                        'action'    => $action['stage'],
                        'message' => "{$e->getMessage()} on line {$e->getLine()} of file {$e->getFile()}. Error code is {$e->getCode()}",
                        'trace' => $e->getTraceAsString()
                    ];
                    $this->errors[] = $error;
                }
            }
        }
    }

    /**
     * @param $rule
     * @param $ruleIndex
     * @param $dataManager
     * @return void
     */
    private function addRulesToDAG($rule, $ruleIndex, &$dataManager): void
    {

        $skip = $rule['skip'] ?? 0;
        $ruleStage = "rule_" . (!empty($rule['stage']) ? $rule['stage'] : $ruleIndex);
        $ruleDependencies = $rule['dependencies'] ?? [];
        $condition = $rule['condition'];
        $actions = $rule['actions'];

        // Define the rule task, which is to evaluate its condition.
        $ruleTask = new Task($ruleStage, $dataManager->getData(), function() use ($ruleStage, $dataManager, $skip, $condition) {

            // Evaluate the rule's condition to determine if actions should be skipped
            $isSkipped = $skip == 1 || !self::evaluateCondition($dataManager->getData(), $condition);

            return ['isSkipped' => $isSkipped, 'data' => $dataManager->getData()];
        });

        // Add the rule task to the workflow.
        $this->workflowDAG->addTask($ruleTask);

        // Add the actions
        foreach ($actions as $actionIndex => $action) {

            $actionStage = "action_" . ( !empty($action['stage']) ? $action['stage'] : $actionIndex);
            $actionDependencies = $action['dependencies'] ?? [];
            $skipAction = $action['skip'] ?? 0;

            $actionTask = new Task($actionStage, $dataManager->getData(), function($parentResults) use (&$dataManager,$actionStage, $action, $skipAction) {

                $shouldSkipRule = array_reduce($parentResults, function($carry, $result) {
                    return $carry || (isset($result['isSkipped']) && $result['isSkipped']);
                }, false);

                // We must pass data by reference since all transformers must work on the same data
                $dataToUse = &$dataManager->getData();

                if (!$shouldSkipRule && !$skipAction) {

                    // Execute the action logic here if the rule was not skipped
                    if (Utils::isObject($dataToUse)) {
                        $this->executeAction($dataToUse, $action);
                    } else {
                        $temp = [];
                        foreach ($dataToUse as &$item) {
                            $this->executeAction($item, $action);

                            if (Utils::isObject($item)) {
                                $temp[] = $item;
                            } else {
                                foreach ($item as $it) {
                                    $temp[] = $it;
                                }
                            }
                        }
                        $dataToUse = $temp;
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

    /**
     * @param $condition
     * @param $pathValue
     * @return void
     */
    private static function addPathValueToCondition(&$condition, $pathValue): void
    {

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
    public static function evaluateCondition($data, $condition, $useDataAsPathValue = false): mixed
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
    private function executeAction(&$data, $action): void
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
        return match ($action['action']) {
            'add' => AddAction::class,
            'subtract' => SubtractAction::class,
            'multiply' => MultiplyAction::class,
            'divide' => DivideAction::class,
            'set' => SetValueAction::class,
            'delete' => DeleteValueAction::class,
            'remove_path' => RemovePathAction::class,
            'function' => FunctionAction::class,
            default => throw new \InvalidArgumentException('Unknown action type: ' . $action['action']),
        };
    }
}
