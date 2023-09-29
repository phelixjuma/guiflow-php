<?php

namespace PhelixJuma\DataTransformer\Conditions;

use FuzzyWuzzy\Fuzz;
use PhelixJuma\DataTransformer\Exceptions\UnknownOperatorException;
use PhelixJuma\DataTransformer\Utils\PathResolver;
use PhelixJuma\DataTransformer\Utils\Utils;

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
        $similarityThreshold = $this->condition['similarity_threshold'] ?? null;

        // Handle wildcard paths
        if (is_array($pathValues)) {
            foreach ($pathValues as $pathValue) {
                if (self::compare($pathValue, $operator, $value, $similarityThreshold)) {
                    return true; // Return true as soon as one match is found
                }
            }
            return false; // If no match is found, return false
        } else {
            return self::compare($pathValues, $operator, $value, $similarityThreshold);
        }
    }

    /**
     * @param $pathValue
     * @param $operator
     * @param $value
     * @return bool
     * @throws UnknownOperatorException
     */
    public static function compare($pathValue, $operator, $value, $similarityThreshold = null): bool
    {
        $fuzz = new Fuzz();

        // lowercase
        $pathValue = is_string($pathValue) ? Utils::cleanText($pathValue) : $pathValue;
        $value = is_string($value) ? Utils::cleanText($value)  : $value;

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
                return in_array($pathValue, (array)$value);
            case 'not in':
                return !in_array($pathValue, (array)$value);
            case 'true':
                return $pathValue === true;
            case 'false':
                return $pathValue === false;
            case 'like':
                $pattern = str_replace('%', '.*', $value);
                return preg_match("/$pattern/", $pathValue) === 1;
            case 'similar_to':
                return $fuzz->partialRatio($pathValue, $value) >= $similarityThreshold;
            default:
                throw new UnknownOperatorException("Unknown operator: $operator");
        }
    }
}
