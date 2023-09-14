<?php

namespace PhelixJuma\DataTransformer\Conditions;

use PhelixJuma\DataTransformer\Exceptions\UnknownOperatorException;
use PhelixJuma\DataTransformer\Utils\PathResolver;

class SimpleCondition implements ConditionInterface
{
    private $condition;
    private $pathResolver;

    public function __construct($condition, PathResolver $pathResolver)
    {
        $this->condition = $condition;
        $this->pathResolver = $pathResolver;
    }

    /**
     * @param $data
     * @return bool
     * @throws UnknownOperatorException
     */
    public function evaluate($data): bool
    {

        if ($this->condition == 'always') {
            return true;
        }

        $pathValues = $this->pathResolver::getValueByPath($data, $this->condition['path']);

        $operator = $this->condition['operator'];
        $value = $this->condition['value'] ?? null;

        // Handle wildcard paths
        if (is_array($pathValues)) {
            foreach ($pathValues as $pathValue) {
                if ($this->compare($pathValue, $operator, $value)) {
                    return true; // Return true as soon as one match is found
                }
            }
            return false; // If no match is found, return false
        } else {
            return $this->compare($pathValues, $operator, $value);
        }
    }

    /**
     * @param $pathValue
     * @param $operator
     * @param $value
     * @return bool
     * @throws UnknownOperatorException
     */
    private function compare($pathValue, $operator, $value)
    {
        // The existing switch case logic...
        switch ($operator) {
            case '==':
                return $pathValue == $value;
            case '!=':
                return $pathValue != $value;
            case '>':
                return $pathValue > $value;
            case '>=':
                return $pathValue >= $value;
            case '<':
                return $pathValue < $value;
            case '<=':
                return $pathValue <= $value;
            case 'contains':
                return strpos($pathValue, $value) !== false;
            case 'exists':
                return $pathValue == 0 || !empty($pathValue);
            case 'regex':
                return preg_match($value, $pathValue) === 1;
            case 'in':
                return in_array($pathValue, $value);
            case 'not in':
                return !in_array($pathValue, $value);
            case 'true':
                return $pathValue === true;
            case 'false':
                return $pathValue === false;
            case 'like':
                $pattern = str_replace('%', '.*', $value);
                return preg_match("/$pattern/", $pathValue) === 1;
            default:
                throw new UnknownOperatorException("Unknown operator: $operator");
        }
    }
}
