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
use PhelixJuma\GUIFlow\Utils\ConfigurationValidator;
use PhelixJuma\GUIFlow\Utils\PathResolver;
use PhelixJuma\GUIFlow\Utils\Utils;
use Ramsey\Uuid\Uuid;

class Workflow
{

    private $id;
    private $parent_id;

    private array $config;
    private object $functionsClass;

    public $errors = [];

    /**
     * @param $id
     * @param array $config
     * @param object $functionsClass
     * @throws Exception
     */
    public function __construct($id, array $config, object $functionsClass)
    {
        $this->id = $id;
        $this->config = $config;
        $this->functionsClass = $functionsClass;

        // Validate the configuration against the schema
        ConfigurationValidator::validate($this->config, 'v3');
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
        return null;
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

        return $this->runWorkFlowSerial($data);
    }

    /**
     * @param $inputData
     * @return string
     */
    private function runWorkFlowSerial(&$inputData): string
    {

        $workflowExecutionId = Uuid::uuid4()->toString();

        // We execute within a coroutine environment to support parallelization
        try {

            $config = json_decode(json_encode($this->config), JSON_FORCE_OBJECT);

            foreach ($config as $rule) {

                try {

                    if (Utils::isObject($inputData)) {
                        $this->executeRuleSerial($rule, $inputData);
                    } else {

                        $tempData = [];

                        $results = [];
                        //$tasks = [];
                        foreach ($inputData as $data) {

                            $this->executeRuleSerial($rule, $data);

                            if (Utils::isObject($data)) {
                                // An object. Set to temp data
                                $results[] = [$data];
                            } else {
                                $results[] = $data;
                            }
                        }

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
                            $results = [];
                            foreach ($data as $datum) {

                                $this->executeAction($datum, $action);

                                if (Utils::isObject($datum)) {
                                    $results[] = [$datum];
                                } else {
                                    $results[] = $datum;
                                }

                            }

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

                } catch (\Exception|\Throwable $e ) {
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

    protected static function resolveInItemPaths($data, $param) {
        if (is_array($param)) {
            // Check if the array contains only the 'path' key
            if (count($param) === 1 && isset($param['in_item_path'])) {
                $paramPath = $param['in_item_path'];
                return PathResolver::getValueByPath($data, $paramPath);
            } else {
                // Recursively resolve each element in the array
                $resolvedArray = [];
                foreach ($param as $key => $value) {
                    $resolvedArray[$key] = self::resolveInItemPaths($data, $value);
                }
                return $resolvedArray;
            }
        }
        // If param is not an array, return it as-is
        return $param;
    }

    /**
     * @param $data
     * @param $condition
     * @param $useDataAsPathValue
     * @return mixed
     */
    public static function evaluateCondition($data, $condition, $useDataAsPathValue = false): mixed
    {

        // We resolve in item paths 
        $condition = self::resolveInItemPaths($data, $condition);
        
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
