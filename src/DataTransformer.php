<?php

namespace PhelixJuma\DataTransformer;

use PhelixJuma\DataTransformer\Actions\AddAction;
use PhelixJuma\DataTransformer\Actions\DeleteValueAction;
use PhelixJuma\DataTransformer\Actions\DivideAction;
use PhelixJuma\DataTransformer\Actions\ModelMappingAction;
use PhelixJuma\DataTransformer\Actions\FunctionAction;
use PhelixJuma\DataTransformer\Actions\MultiplyAction;
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
    private PathResolver $pathResolver;


    public function __construct(array $config, object $functionsClass)
    {
        $this->config = $config;
        $this->functionsClass = $functionsClass;
        $this->pathResolver = new PathResolver();

        // Validate the configuration against the schema
        ConfigurationValidator::validate($this->config);
    }

    /**
     * @param $data
     * @return void
     */
    public function transform(&$data): void
    {
        $config = json_decode(json_encode($this->config), JSON_FORCE_OBJECT);

        foreach ($config as $rule) {

            $condition = $rule['condition'];
            $actions = $rule['actions'];

            // Evaluate the condition
            if ($this->evaluateCondition($data, $condition)) {
                // Execute the actions
                foreach ($actions as $action) {
                    $this->executeAction($data, $action);
                }
            }
        }
    }

    private function evaluateCondition($data, $condition)
    {
        $conditionClass = $this->getConditionClass($condition);
        $conditionInstance = new $conditionClass($condition, $this->pathResolver);
        return $conditionInstance->evaluate($data);
    }

    /**
     * @param $data
     * @param $action
     * @return void
     */
    private function executeAction(&$data, $action)
    {
        $actionClass = $this->getActionClass($action);

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
            case 'function':
                $actionInstance = new $actionClass($action['path'], [$this->functionsClass, $action['function']], $action['args'] ?? [], $action['newField'] ?? null);
                break;
            default:
                throw new \InvalidArgumentException('Unknown action type: ' . $action['action']);
        }
        $actionInstance->execute($data);
    }


    private function getConditionClass($condition): string
    {
        if (isset($condition['operator']) && in_array(strtolower($condition['operator']), ['and', 'or'])) {
            return CompositeCondition::class;
        } else {
            return SimpleCondition::class;
        }
    }

    private function getActionClass($action): string
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
            case 'function':
                return FunctionAction::class;
            default:
                throw new \InvalidArgumentException('Unknown action type: ' . $action['action']);
        }
    }
}
