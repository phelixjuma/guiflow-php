<?php

namespace PhelixJuma\GUIFlow\Conditions;

use FuzzyWuzzy\Fuzz;
use PhelixJuma\GUIFlow\Exceptions\UnknownOperatorException;
use PhelixJuma\GUIFlow\Utils\DataValidator;
use PhelixJuma\GUIFlow\Utils\PathResolver;
use PhelixJuma\GUIFlow\Utils\Utils;

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
     * @throws \Exception
     */
    public function evaluate($data): bool
    {

        if ($this->condition == 'always') {
            return true;
        }

        $hasWildcardPaths = !empty($this->condition['path']) && str_contains($this->condition['path'], "*");

        $pathValues = $data;
        if (isset($this->condition['path_value'])) {
            $pathValues = $this->condition['path_value'];
        } elseif(!empty($this->condition['path'])) {
            $pathValues = $this->pathResolver::getValueByPath($data, $this->condition['path']);
        }

        $operator = $this->condition['operator'];
        $conditionValue = $this->condition['value'] ?? null;

        if (isset($conditionValue['path'])) {
            $value = $this->pathResolver::getValueByPath($data, $conditionValue['path']);
        } else {
            $value = $conditionValue;
        }
        $similarityThreshold = $this->condition['similarity_threshold'] ?? null;

        // support lists
        $supportsListByListComparison = in_array($operator, [
            "lists_have_intersection",
            "lists_not_have_intersection",
            "list_contains_all",
            "list_not_contains_all"
            ]);

        // Handle wildcard paths
        if ($hasWildcardPaths && !$supportsListByListComparison && is_array($pathValues) && !Utils::isObject($pathValues)) {
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
     * @param $similarityThreshold
     * @param $tokenizeSimilarity
     * @return bool
     * @throws \Exception
     */
    public static function compare($pathValue, $operator, $value, $similarityThreshold = null, $tokenizeSimilarity = false): bool
    {
        $fuzz = new Fuzz();

        // clean path value
        if (is_string($pathValue)) {
            $pathValue = Utils::cleanText($pathValue);
        } elseif(is_array($pathValue) && is_string($pathValue[0])) {
            array_walk($pathValue, function (&$v, $k) {
               $v = Utils::cleanText($v);
            });
        }

        // clean value
        if (is_string($value)) {
            $value = Utils::cleanText($value);
        } elseif(is_array($value) && is_string($value[0])) {
            array_walk($value, function (&$v, $k) {
                $v = Utils::cleanText($v);
            });
        }

        // The existing switch case logic...
        try {
            switch ($operator) {
                case '==':
                    return $pathValue == $value;
                case '!=':
                    return $pathValue != $value;
                case 'gt':
                    return $pathValue > $value;
                case 'gte':
                    return $pathValue >= $value;
                case 'lt':
                    return $pathValue < $value;
                case 'lte':
                    return $pathValue <= $value;
                case 'strleneq':
                    return is_string($pathValue) && strlen($pathValue) == $value;
                case 'strlenneq':
                    return is_string($pathValue) && strlen($pathValue) != $value;
                case 'strlengt':
                    return is_string($pathValue) && strlen($pathValue) > $value;
                case 'strlengte':
                    return is_string($pathValue) && strlen($pathValue) >= $value;
                case 'strlenlt':
                    return is_string($pathValue) && strlen($pathValue) < $value;
                case 'strlenlte':
                    return is_string($pathValue) && strlen($pathValue) <= $value;
                case 'sizeofeq':
                    $size = Utils::length($pathValue);
                    return intval($size) == intval($value);
                case 'sizeofneq':
                    $size = Utils::length($pathValue);
                    return $size != $value;
                case 'sizeofgt':
                    $size = Utils::length($pathValue);
                    return $size > $value;
                case 'sizeofgte':
                    $size = Utils::length($pathValue);
                    return $size >= $value;
                case 'sizeoflt':
                    $size = Utils::length($pathValue);
                    return $size < $value;
                case 'sizeoflte':
                    $size = Utils::length($pathValue);
                    return $size <= $value;
                case 'contains':
                    return !empty($pathValue) && !empty($value) && str_contains($pathValue, $value);
                case 'not contains':
                    return empty($pathValue) || empty($value) || !str_contains($pathValue, $value);
                case 'matches':
                    return is_string($pathValue) && preg_match('/' . Utils::custom_preg_escape(Utils::full_unescape($value)) . '/i', $pathValue);
                case 'is_substring':
                    return !empty($pathValue) && !empty($value) && str_contains($value, $pathValue);
                case 'is_not_substring':
                    return empty($pathValue) || empty($value) || !str_contains($value, $pathValue);
                case 'not matches':
                    return !is_string($pathValue) || !preg_match('/' . Utils::custom_preg_escape(Utils::full_unescape($value)) . '/i', $pathValue);
                case 'exists':
                    // For arrays, we remove empty values
                    if (is_array($pathValue) && !Utils::isObject($pathValue)) {
                        $pathValue = array_filter($pathValue);
                    }
                    return $pathValue === 0 || !empty($pathValue);
                case 'not exists':
                    // For arrays, we remove empty values
                    if (is_array($pathValue) && !Utils::isObject($pathValue)) {
                        $pathValue = array_filter($pathValue);
                    }
                    return empty($pathValue) && $pathValue !== 0;
                case 'regex':
                    return is_string($pathValue) && preg_match($value, $pathValue) === 1;
                case 'in':
                    return in_array($pathValue, (array)$value);
                case 'not in':
                    return !in_array($pathValue, (array)$value);
                case 'in list all':
                    if (is_array($value)) {
                        foreach ($value as $v) {
                            $pattern = '/' . Utils::custom_preg_escape(Utils::full_unescape($v)) . '/i';
                            if (!is_string($pathValue)  || !preg_match($pattern, $pathValue)) {
                                return false;
                            }
                        }
                        return true;
                    }
                    return is_string($pathValue) && preg_match('/' . Utils::custom_preg_escape(Utils::full_unescape($value)) . '/i', $pathValue);
                case 'not in list all':
                    if (is_array($value)) {
                        foreach ($value as $v) {
                            $pattern = '/' . Utils::custom_preg_escape(Utils::full_unescape($v)) . '/i';
                            if (is_string($pathValue) && preg_match($pattern, $pathValue)) {
                                return false;
                            }
                        }
                        return true;
                    }
                    return !is_string($pathValue)|| !preg_match('/' . Utils::custom_preg_escape(Utils::full_unescape($value)) . '/i', $pathValue);
                case 'in list any':
                    if (is_array($value)) {
                        foreach ($value as $v) {
                            $pattern = '/' . Utils::custom_preg_escape(Utils::full_unescape($v)) . '/i';

                            if (is_string($pathValue) && preg_match($pattern, $pathValue)) {
                                return true;
                            }
                            if (preg_last_error() !== PREG_NO_ERROR) {
                                throw new \Exception("Preg Error when matching ".json_encode($pathValue)." against ".json_encode($pattern)." : ".Utils::getPregError(preg_last_error()));
                            }
                        }
                        return false;
                    }
                    return is_string($pathValue) && preg_match('/' . Utils::custom_preg_escape(Utils::full_unescape($value)) . '/i', $pathValue);
                case 'not in list any':
                    if (is_array($value)) {
                        foreach ($value as $v) {
                            $pattern = '/' . Utils::custom_preg_escape(Utils::full_unescape($v)) . '/i';
                            if (!is_string($pathValue)  || !preg_match($pattern, $pathValue)) {
                                return true;
                            }
                            if (preg_last_error() !== PREG_NO_ERROR) {
                                throw new \Exception("Preg Error when matching ".json_encode($pathValue)." against ".json_encode($pattern)." : ".Utils::getPregError(preg_last_error()));
                            }
                        }
                        return false;
                    }
                    return !is_string($pathValue) || !preg_match('/' . Utils::custom_preg_escape(Utils::full_unescape($value)) . '/i', $pathValue);
                case 'true':
                    return $pathValue === true;
                case 'false':
                    return $pathValue === false;
                case 'like':
                    $pattern = str_replace('%', '.*', $value);
                    return is_string($pathValue) && preg_match("/$pattern/", $pathValue) === 1;
                case 'similar_to':
                    if ($tokenizeSimilarity) {
                        return $fuzz->tokenSortPartialRatio($pathValue, $value) >= $similarityThreshold;
                    }
                    return $fuzz->partialRatio($pathValue, $value) >= $similarityThreshold;
                case 'not similar_to':
                    if ($tokenizeSimilarity) {
                        return $fuzz->tokenSortPartialRatio($pathValue, $value) < $similarityThreshold;
                    }
                    return $fuzz->partialRatio($pathValue, $value) < $similarityThreshold;
                case 'validates':
                    return DataValidator::validateDataStructure($pathValue, $value, false);
                case 'not validates':
                    return !DataValidator::validateDataStructure($pathValue, $value, false);
                case 'lists_have_intersection':
                    return count(array_intersect($value, $pathValue)) > 0;
                case 'lists_not_have_intersection':
                    return count(array_intersect($value, $pathValue)) == 0;
                case 'list_contains_all':
                    $isTrue = empty(array_diff($value, $pathValue));
                    return  $isTrue;
                case 'list_not_contains_all':
                    return !empty(array_diff($value, $pathValue));
                default:
                    throw new UnknownOperatorException("Unknown operator: $operator");
            }
        } catch (\Exception | \Throwable $e) {
            throw new \Exception("simple condition error on value: ".json_encode($value)." and path value: ".json_encode($pathValue).". Error says ".$e->getMessage(). ". Line: ".$e->getLine()." on file: ".$e->getFile());
        }
    }
}
