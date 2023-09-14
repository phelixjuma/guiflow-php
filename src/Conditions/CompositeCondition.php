<?php

namespace PhelixJuma\DataTransformer\Conditions;

use PhelixJuma\DataTransformer\Utils\PathResolver;

class CompositeCondition implements ConditionInterface
{
    private $condition;
    private $pathResolver;

    public function __construct($condition, PathResolver $pathResolver)
    {
        $this->condition = $condition;
        $this->pathResolver = $pathResolver;
    }

    public function evaluate($data)
    {
       if (strtolower($this->condition['operator']) == 'and') {

            foreach ($this->condition['conditions'] as $subcondition) {
                $conditionClass = $this->getConditionClass($subcondition);
                $conditionInstance = new $conditionClass($subcondition, $this->pathResolver);
                if (!$conditionInstance->evaluate($data)) {
                    return false;
                }
            }
            return true;
        }
        elseif (strtolower($this->condition['operator']) == 'or') {
            foreach ($this->condition['conditions'] as $subcondition) {
                $conditionClass = $this->getConditionClass($subcondition);
                $conditionInstance = new $conditionClass($subcondition, $this->pathResolver);
                if ($conditionInstance->evaluate($data)) {
                    return true;
                }
            }
            return false;
        } else {
            throw new \InvalidArgumentException('Invalid composite condition');
        }
    }

    private function getConditionClass($condition): string
    {
        if (isset($condition['operator']) && in_array(strtolower($condition['operator']), ['and', 'or'])) {
            return CompositeCondition::class;
        } else {
            return SimpleCondition::class;
        }
    }
}
