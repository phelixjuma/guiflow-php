<?php

namespace PhelixJuma\GUIFlow\Utils;

use FuzzyWuzzy\Fuzz;
use PhelixJuma\GUIFlow\Conditions\SimpleCondition;

class Filter
{

    // operators
    const EQUAL = '==';
    const NOT_EQUAL = '!=';
    const GREATER = '>';
    const GREATER_OR_EQUAL = '>=';
    const LESS = '<';
    const LESS_OR_EQUAL = '<=';
    const IN = 'in';
    const NOT_IN = 'not in';
    const REGEX = 'regex';
    const TRUE = 'true';
    const FALSE = 'false';
    const EMPTY = 'empty';
    const NOT_EMPTY = 'not empty';
    const STARTS_WITH = 'startswith';
    const ENDS_WITH = 'endswith';
    const SIMILAR_TO = 'similar_to';
    const CONTAINS  = 'contains';

    const DEFAULT_THRESHOLD = 80;

    public function __construct() {
    }

    /**
     * @param $data
     * @param $pattern
     * @return void
     */
    private static function excludePatternFromData(&$data, $pattern) {

        if (!is_array($data)) {
            $data = preg_replace("/$pattern/i", '', $data);

            // Trim and remove multiple spaces to clean up the result
            $data = preg_replace('/\s+/', ' ', trim($data));

        } else {
            array_walk($data, function (&$v, $k) use($pattern) {
                self::excludePatternFromData($v, $pattern);
            }) ;
        }
    }

    /**
     * @param $value
     * @param $term
     * @param $mode
     * @param int $similarityThreshold
     * @param $termExclusionPattern
     * @param $valueExclusionPattern
     * @return bool|int
     * @throws \Exception
     */
    private static function matchValueAgainstFilter($value, $term, $mode, int $similarityThreshold=self::DEFAULT_THRESHOLD, $termExclusionPattern = null, $valueExclusionPattern=null): bool|int
    {
        if (is_null($value)) {
            return false;
        }
        $term = is_array($term) ? array_map(function($item) {
            return is_string($item) ? strtolower(trim($item)) : $item;
        }, $term) : strtolower(trim($term));

        $value = is_string($value) ? strtolower(trim($value)) : $value;

        // if exclusion pattern is set, we clean the term and value using the pattern.
        if (!empty($termExclusionPattern)) {
            self::excludePatternFromData($term, $termExclusionPattern);
        }
        if (!empty($valueExclusionPattern)) {
            self::excludePatternFromData($value, $valueExclusionPattern);
        }

        $fuzz = new Fuzz();

        return match ($mode) {
            self::EQUAL => $term == $value,
            self::NOT_EQUAL => $term != $value,
            self::GREATER => $value > $term,
            self::GREATER_OR_EQUAL => $value >= $term,
            self::LESS => $value < $term,
            self::LESS_OR_EQUAL => $value <= $term,
            self::IN => in_array($value, $term),
            self::NOT_IN => !in_array($value, $term),
            self::REGEX => preg_match($term, $value),
            self::TRUE => $value == true,
            self::FALSE => $value == false,
            self::EMPTY => empty($value),
            self::NOT_EMPTY => !empty($value),
            self::STARTS_WITH => str_starts_with($value, $term),
            self::ENDS_WITH => str_ends_with($value, $term),
            self::SIMILAR_TO => $fuzz->tokenSetRatio($value, $term) >= $similarityThreshold,
            default => SimpleCondition::compare($value, $mode, $term, $similarityThreshold)
        };
    }

    /**
     * @param $value
     * @param $filters
     * @return bool|int
     */
    private static function matchesCriteria($value, $filters): bool|int
    {

        // simple condition
        if (!isset($filters['operator'])) {

            $keyIsNestedPath = !empty($filters['key']) && str_contains($filters['key'], ".");

            // For an associative array, we get value based on key, if not nested
            if (Utils::isObject($value) && !$keyIsNestedPath) {
                if (!isset($value[$filters['key']])) {
                    $value = null;
                } else {
                    $value = $value[$filters['key']];
                }
            }
            // For an array where key is nested path
            elseif (is_array($value) && $keyIsNestedPath) {
                $value = PathResolver::getValueByPath($value, $filters['key']);
            }

            //$value = PathResolver::getValueByPath($value, $filters['key']);

            if ($value === null) {
                // null value indicates the key does not exist and so the filter returns true ie no filter is done
                return true;
            }

            return self::matchValueAgainstFilter($value,
                $filters['term'],
                $filters['mode'] ?? self::CONTAINS,
                $filters['threshold'] ?? self::DEFAULT_THRESHOLD,
            $filters['term_exclusion_pattern'] ?? null,
            $filters['value_exclusion_pattern'] ?? null);
        }

        // composite conditions
        if (strtolower($filters['operator']) == 'and') {

            foreach ($filters['conditions'] as $filter) {

                if (!self::matchesCriteria($value, $filter)) {
                    return false;
                }
            }
            return true;
        }
        elseif (strtolower($filters['operator']) == 'or') {
            foreach ($filters['conditions'] as $filter) {
                if (self::matchesCriteria($value, $filter)) {
                    return true;
                }
            }
            return false;
        } else {
            throw new \InvalidArgumentException('Invalid composite condition');
        }
    }

    /**
     * @param $array
     * @param $filters
     * @return array|mixed
     */
    public static function filterArray($array, $filters)
    {

        if (empty($array)) {
            return $array;
        }

        foreach ($array as $key => $value) {

            if (is_array($value)) {

                // Check if it's an associative array (object)
                if (array_keys($value) !== range(0, count($value) - 1)) {

                    if (!self::matchesCriteria($value, $filters)) {
                        unset($array[$key]);
                    }

                } else {

                    $array[$key] = self::filterArray($value, $filters);
                    if (empty($array[$key])) {
                        unset($array[$key]);
                    }
                }
            } else {
                if (!self::matchesCriteria($value, $filters)) {
                    unset($array[$key]);
                }
            }
        }
        return array_values($array);  // Resetting the keys
    }

}
