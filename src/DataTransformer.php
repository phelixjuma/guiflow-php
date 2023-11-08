<?php

namespace PhelixJuma\DataTransformer;

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


    public function __construct(array $config, object $functionsClass)
    {
        $this->config = $config;
        $this->functionsClass = $functionsClass;
        //$this->pathResolver = new PathResolver();

        // Validate the configuration against the schema
        ConfigurationValidator::validate($this->config);
    }

    /**
     * @param $data
     * @return void
     */
    public function transform(&$data) {

        $dataCopy = $data;
        $data = [];

        if (is_array($dataCopy)) {
            if (self::isObject($dataCopy)) {

                // For an object, we transform it
                $this->transformObject($dataCopy);

                // Set the response into data: checking if the response has been split or not.
                if (self::isObject($dataCopy)) {
                    $data[] = $dataCopy;
                } else {
                    $data = $dataCopy;
                }

            } else {
                // it's an array, we loop
                foreach ($dataCopy as $item) {

                    $this->transformObject($item);

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
     * @return void
     */
    private function transformObject(&$data): void
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

                            } catch (\Exception|\Throwable $e ) { }
                        }
                    }
                } catch (\Exception|\Throwable $e ) {
                }
            }

        } catch (\Exception|\Throwable $e ) {
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

        if (isset($condition['operator']) && isset($condition['value'])) {
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
        if ($useDataAsPathValue !== false) {
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
                $actionInstance = new $actionClass($action['path'], $action['value'] ?? null, $action['valueFromField'] ?? null, $action['valueMapping'] ?? null);
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
